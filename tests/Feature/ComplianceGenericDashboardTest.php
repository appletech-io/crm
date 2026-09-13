<?php

use App\Filament\Pages\Dashboards\ComplianceGenericDashboard;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Widgets\ComplianceGenericKpiOverview;
use App\Filament\Widgets\ComplianceItemVettingTable;
use App\Models\Candidate;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function assignGenericComplianceStatus(Candidate $candidate, Industry $industry, int $companyId, string $statusName): void
{
    $status = CandidateStatus::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industry->id,
        'name' => $statusName,
    ]);

    CandidateCandidateStatus::create([
        'model_type' => Candidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);
}

function makeGenericComplianceField(int $companyId, Industry $industry, string $dataType): ComplianceItemField
{
    $item = ComplianceItem::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industry->id,
    ]);

    return ComplianceItemField::factory()->create([
        'compliance_item_id' => $item->id,
        'data_type' => $dataType,
    ]);
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'construction']);
    $this->company->industries()->attach($this->industry->id);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->industries()->attach($this->industry);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);
});

test('the generic compliance dashboard composes one kpi overview and three vetting buckets', function () {
    $widgets = (new ComplianceGenericDashboard)->getWidgets();

    expect($widgets)->toHaveCount(4)
        ->and($widgets[0])->toBe(ComplianceGenericKpiOverview::class);

    foreach (array_slice($widgets, 1) as $bucket) {
        expect($bucket->widget)->toBe(ComplianceItemVettingTable::class);
    }
});

test('outstanding in vetting counts generic candidates currently assigned the Vetting status', function () {
    $vettingCandidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    assignGenericComplianceStatus($vettingCandidate, $this->industry, $this->company->id, 'Vetting');

    $liveCandidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    assignGenericComplianceStatus($liveCandidate, $this->industry, $this->company->id, 'Live');

    $count = Livewire::test(ComplianceGenericKpiOverview::class)->instance()->outstandingInVettingCount();

    expect($count)->toBe(1);
});

test('a candidate with none of their compliance items complete falls in the Not Complete bucket', function () {
    $field = makeGenericComplianceField($this->company->id, $this->industry, 'text');

    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    assignGenericComplianceStatus($candidate, $this->industry, $this->company->id, 'Vetting');

    Livewire::test(ComplianceItemVettingTable::class, ['ratioFrom' => 0.0, 'ratioTo' => 1 / 3])
        ->assertCanSeeTableRecords([$candidate]);

    Livewire::test(ComplianceItemVettingTable::class, ['ratioFrom' => 2 / 3, 'ratioTo' => 1.0])
        ->assertCanNotSeeTableRecords([$candidate]);
});

test('a candidate with every compliance item complete falls in the Almost Complete bucket', function () {
    $field = makeGenericComplianceField($this->company->id, $this->industry, 'text');

    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    assignGenericComplianceStatus($candidate, $this->industry, $this->company->id, 'Vetting');
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'text_value' => 'ABC123']);

    Livewire::test(ComplianceItemVettingTable::class, ['ratioFrom' => 2 / 3, 'ratioTo' => 1.0])
        ->assertCanSeeTableRecords([$candidate]);
});

test('a candidate with no compliance items configured at all is treated as fully complete, not zero', function () {
    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    assignGenericComplianceStatus($candidate, $this->industry, $this->company->id, 'Vetting');

    Livewire::test(ComplianceItemVettingTable::class, ['ratioFrom' => 2 / 3, 'ratioTo' => 1.0])
        ->assertCanSeeTableRecords([$candidate]);

    Livewire::test(ComplianceItemVettingTable::class, ['ratioFrom' => 0.0, 'ratioTo' => 1 / 3])
        ->assertCanNotSeeTableRecords([$candidate]);
});

test('a candidate who has already been marked compliance complete no longer appears in any bucket', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'compliance_completed_at' => now(),
    ]);
    assignGenericComplianceStatus($candidate, $this->industry, $this->company->id, 'Vetting');

    Livewire::test(ComplianceItemVettingTable::class, ['ratioFrom' => 2 / 3, 'ratioTo' => 1.0])
        ->assertCanNotSeeTableRecords([$candidate]);
});

test('the progress label shows how many compliance items are complete out of the total', function () {
    $completeField = makeGenericComplianceField($this->company->id, $this->industry, 'text');
    $incompleteField = makeGenericComplianceField($this->company->id, $this->industry, 'text');

    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    assignGenericComplianceStatus($candidate, $this->industry, $this->company->id, 'Vetting');
    $candidate->complianceValues()->create(['compliance_item_field_id' => $completeField->id, 'text_value' => 'filled']);

    $label = Livewire::test(ComplianceItemVettingTable::class, ['ratioFrom' => 0.0, 'ratioTo' => 1.0])
        ->instance()
        ->progressLabel($candidate);

    expect($label)->toBe('1 / 2 complete');
});

test('the vetting bucket name column links to the candidate edit page', function () {
    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    assignGenericComplianceStatus($candidate, $this->industry, $this->company->id, 'Vetting');

    $url = Livewire::test(ComplianceItemVettingTable::class, ['ratioFrom' => 0.0, 'ratioTo' => 1.0])
        ->instance()
        ->candidateUrl($candidate);

    expect($url)->toBe(CandidateResource::getUrl('edit', ['record' => $candidate]));
});

test('mark compliance complete is disabled until every compliance item is complete', function () {
    $field = makeGenericComplianceField($this->company->id, $this->industry, 'text');

    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionDisabled('markComplianceComplete');

    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'text_value' => 'filled']);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionEnabled('markComplianceComplete');
});

test('mark compliance complete is disabled for a candidate with no compliance items configured at all', function () {
    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionDisabled('markComplianceComplete');
});

test('clicking mark compliance complete stamps compliance_completed_at and compliance_completed_by', function () {
    $field = makeGenericComplianceField($this->company->id, $this->industry, 'text');

    $candidate = Candidate::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'text_value' => 'filled']);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->callAction('markComplianceComplete');

    $candidate->refresh();

    expect($candidate->compliance_completed_at)->not->toBeNull()
        ->and($candidate->compliance_completed_by)->toBe($this->admin->id);
});

test('mark compliance complete is disabled once already marked complete', function () {
    $field = makeGenericComplianceField($this->company->id, $this->industry, 'text');

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'compliance_completed_at' => now(),
    ]);
    $candidate->complianceValues()->create(['compliance_item_field_id' => $field->id, 'text_value' => 'filled']);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionDisabled('markComplianceComplete');
});
