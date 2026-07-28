<?php

use App\Filament\Resources\HealthcareVetting\HealthcareVettingResource;
use App\Filament\Widgets\ComplianceHealthcareKpiOverview;
use App\Filament\Widgets\ComplianceVettingTable;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateStatus;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function assignHealthcareComplianceStatus(HealthcareCandidate $candidate, Industry $industry, string $companyId, string $statusName): void
{
    $status = CandidateStatus::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industry->id,
        'name' => $statusName,
    ]);

    CandidateCandidateStatus::create([
        'model_type' => HealthcareCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);
}

function healthcareNotCompleteTableProperties(): array
{
    return [
        'candidateModelClass' => HealthcareCandidate::class,
        'vettingResourceClass' => HealthcareVettingResource::class,
        'stepLabelsList' => [
            'Personal Details', 'Pay Rates', 'Skills', 'Documents', 'Security Checks', 'Professional Registration', 'DBS', 'References', 'Confirm',
        ],
        'stepFrom' => 1,
        'stepTo' => 3,
        'bucketHeading' => 'Not Complete',
        'bucketColor' => 'danger',
    ];
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('compliance');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('outstanding in vetting counts healthcare candidates currently assigned the Vetting status', function () {
    $vettingCandidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignHealthcareComplianceStatus($vettingCandidate, $this->industry, $this->user->company_id, 'Vetting');

    $liveCandidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignHealthcareComplianceStatus($liveCandidate, $this->industry, $this->user->company_id, 'Live');

    $count = Livewire::test(ComplianceHealthcareKpiOverview::class)->instance()->outstandingInVettingCount();

    expect($count)->toBe(1);
});

test('average days from compliance completion to live averages the application completed_at to compliance_completed_at for healthcare candidates', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'compliance_completed_at' => Carbon::parse('2026-01-11'),
    ]);
    $candidate->application()->create([
        'token' => 'healthcare-token',
        'status' => 'completed',
        'completed_at' => Carbon::parse('2026-01-01'),
    ]);

    $average = Livewire::test(ComplianceHealthcareKpiOverview::class)->instance()->averageDaysFromComplianceCompletionToLive();

    expect($average)->toBe(10.0);
});

test('the healthcare vetting bucket table only shows candidates in vetting whose step falls in range', function () {
    $notComplete = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 2]);
    assignHealthcareComplianceStatus($notComplete, $this->industry, $this->user->company_id, 'Vetting');

    $mostlyComplete = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 5]);
    assignHealthcareComplianceStatus($mostlyComplete, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(ComplianceVettingTable::class, healthcareNotCompleteTableProperties())
        ->assertCanSeeTableRecords([$notComplete])
        ->assertCanNotSeeTableRecords([$mostlyComplete]);
});

test('the healthcare vetting url points to the healthcare vetting wizard for the candidate', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $url = Livewire::test(ComplianceVettingTable::class, healthcareNotCompleteTableProperties())
        ->instance()
        ->vettingUrl($candidate);

    expect($url)->toBe(HealthcareVettingResource::getUrl('edit', ['record' => $candidate]));
});

test('the healthcare step label uses the Professional Registration step name', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 6]);

    $label = Livewire::test(ComplianceVettingTable::class, healthcareNotCompleteTableProperties())
        ->instance()
        ->stepLabel($candidate);

    expect($label)->toBe('Step 6 of 9: Professional Registration');
});
