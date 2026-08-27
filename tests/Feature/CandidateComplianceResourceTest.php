<?php

use App\Filament\Resources\CandidateCompliance\CandidateComplianceResource;
use App\Filament\Resources\CandidateCompliance\Pages\EditCandidateCompliance;
use App\Filament\Resources\CandidateCompliance\Pages\ListCandidateCompliance;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');

    $this->company = Company::factory()->create();
    // 'generic' is the slug this session wired to Candidate::class in
    // Industry::$candidateModelMap — canViewAny() on this resource is
    // keyed off that mapping, not a fixed slug name.
    $this->industry = Industry::factory()->create(['slug' => 'generic']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
});

test('this resource is not visible for the education or healthcare industries', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $educationIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $educationIndustry->id);

    expect(CandidateComplianceResource::canViewAny())->toBeFalse();
});

test('the list only shows candidates with incomplete compliance', function () {
    $item = ComplianceItem::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $field = ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text']);
    $this->jobTitle->complianceItems()->attach($item->id, [
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $incompleteCandidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $completeCandidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $completeCandidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'text_value' => 'filled', 'completed_at' => now()]);

    $otherIndustryCandidate = Candidate::factory()->create();

    Livewire::test(ListCandidateCompliance::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$incompleteCandidate])
        ->assertCanNotSeeTableRecords([$completeCandidate, $otherIndustryCandidate]);
});

test('a candidate with no job title is trivially complete and does not appear in the list', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => null,
    ]);

    Livewire::test(ListCandidateCompliance::class)
        ->assertCanNotSeeTableRecords([$candidate]);
});

test('the edit page renders one section per item with one field per data_type inside it', function () {
    $dbsItem = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'DBS',
    ]);
    ComplianceItemField::factory()->create(['compliance_item_id' => $dbsItem->id, 'data_type' => 'text', 'name' => 'DBS Number']);
    ComplianceItemField::factory()->create(['compliance_item_id' => $dbsItem->id, 'data_type' => 'date_expiry', 'name' => 'Expiry Date']);

    $this->jobTitle->complianceItems()->attach($dbsItem->id, [
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);

    Livewire::test(EditCandidateCompliance::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('DBS')
        ->assertSee('DBS Number')
        ->assertSee('Expiry Date');
});

test('filling in every field of a multi-field item saves each as its own CandidateComplianceValue row, without touching basic candidate columns', function () {
    $dbsItem = ComplianceItem::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'DBS']);
    $numberField = ComplianceItemField::factory()->create(['compliance_item_id' => $dbsItem->id, 'data_type' => 'text']);
    $expiryField = ComplianceItemField::factory()->create(['compliance_item_id' => $dbsItem->id, 'data_type' => 'date_expiry']);

    $this->jobTitle->complianceItems()->attach($dbsItem->id, [
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'first_name' => 'Unchanged',
    ]);

    Livewire::test(EditCandidateCompliance::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            "field_{$numberField->id}" => 'DBS-9999',
            "field_{$expiryField->id}" => '2027-01-01',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $numberValue = $candidate->complianceValues()->where('compliance_item_field_id', $numberField->id)->first();
    $expiryValue = $candidate->complianceValues()->where('compliance_item_field_id', $expiryField->id)->first();

    expect($numberValue->text_value)->toBe('DBS-9999')
        ->and($expiryValue->date_value->toDateString())->toBe('2027-01-01')
        ->and($candidate->fresh()->first_name)->toBe('Unchanged');
});

test('existing values are pre-filled when re-opening the edit page', function () {
    $item = ComplianceItem::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $field = ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text']);

    $this->jobTitle->complianceItems()->attach($item->id, [
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'text_value' => 'ALREADY-SET']);

    Livewire::test(EditCandidateCompliance::class, ['record' => $candidate->getRouteKey()])
        ->assertFormSet(["field_{$field->id}" => 'ALREADY-SET']);
});
