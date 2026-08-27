<?php

use App\Filament\EducationCandidate\Pages\Documents;
use App\Filament\Widgets\CandidateDocumentManager;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'generic']);
    $this->company->industries()->attach($this->industry);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->item = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Right to Work',
    ]);
    $this->docField = ComplianceItemField::factory()->create([
        'compliance_item_id' => $this->item->id,
        'data_type' => 'document',
        'name' => 'Document Upload',
    ]);
    $this->jobTitle->complianceItems()->attach($this->item->id, [
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
    ]);
});

test('the candidate can upload a document-type compliance field from their own Documents page', function () {
    $user = User::factory()->create([
        'company_id' => $this->company->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => Candidate::class,
    ]);
    $user->assignRole('candidate');
    $user->industries()->attach($this->industry);
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->callAction(
            TestAction::make('upload')->table(record: "compliance_field_{$this->docField->id}"),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    $value = $this->candidate->complianceValues()->where('compliance_item_field_id', $this->docField->id)->first();

    expect($value)->not->toBeNull()
        ->and($value->document_path)->not->toBeNull()
        ->and($value->completed_at)->not->toBeNull();
    Storage::disk('local')->assertExists($value->document_path);
});

test('removing a compliance document from the Documents page clears the stored value', function () {
    $user = User::factory()->create([
        'company_id' => $this->company->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => Candidate::class,
    ]);
    $user->assignRole('candidate');
    $user->industries()->attach($this->industry);
    $this->actingAs($user);

    Storage::disk('local')->put('candidate-compliance/existing.pdf', 'fake');
    $this->candidate->complianceValues()->create([
        'compliance_item_field_id' => $this->docField->id,
        'document_path' => 'candidate-compliance/existing.pdf',
        'document_name' => 'existing.pdf',
        'completed_at' => now(),
    ]);

    Livewire::test(Documents::class)
        ->set('activeTab', 'documents')
        ->callAction(TestAction::make('remove')->table(record: "compliance_field_{$this->docField->id}"));

    $value = $this->candidate->complianceValues()->where('compliance_item_field_id', $this->docField->id)->first();

    expect($value->document_path)->toBeNull()
        ->and($value->completed_at)->toBeNull();
    Storage::disk('local')->assertMissing('candidate-compliance/existing.pdf');
});

test('staff can upload a document-type compliance field from the Candidates edit page Documents tab', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->assignRole('admin');
    $admin->industries()->attach($this->industry);
    $this->actingAs($admin);

    Cache::put("user.{$admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$admin->id}.active_industry_id", $this->industry->id);

    $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

    Livewire::test(CandidateDocumentManager::class, ['record' => $this->candidate])
        ->callAction(
            TestAction::make('upload')->table(record: "compliance_field_{$this->docField->id}"),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    $value = $this->candidate->complianceValues()->where('compliance_item_field_id', $this->docField->id)->first();

    expect($value)->not->toBeNull()
        ->and($value->document_path)->not->toBeNull();
});

test('a document-type compliance field not attached to the candidate\'s own job title still merges into the Documents page', function () {
    $otherJobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $otherItem = ComplianceItem::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Safeguarding',
    ]);
    $otherField = ComplianceItemField::factory()->create([
        'compliance_item_id' => $otherItem->id,
        'data_type' => 'document',
        'name' => 'Certificate',
    ]);
    $otherJobTitle->complianceItems()->attach($otherItem->id, [
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $user = User::factory()->create([
        'company_id' => $this->company->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => Candidate::class,
    ]);
    $user->assignRole('candidate');
    $user->industries()->attach($this->industry);
    $this->actingAs($user);

    $file = UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf');

    Livewire::test(Documents::class)
        ->assertSee('Safeguarding: Certificate')
        ->callAction(
            TestAction::make('upload')->table(record: "compliance_field_{$otherField->id}"),
            data: ['file' => $file],
        )
        ->assertHasNoActionErrors();

    $value = $this->candidate->complianceValues()->where('compliance_item_field_id', $otherField->id)->first();

    expect($value)->not->toBeNull()
        ->and($value->document_path)->not->toBeNull();
});
