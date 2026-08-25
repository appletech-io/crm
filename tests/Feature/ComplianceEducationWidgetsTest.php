<?php

use App\Filament\Resources\EducationVetting\VettingResource;
use App\Filament\Widgets\ComplianceEducationKpiOverview;
use App\Filament\Widgets\ComplianceVettingTable;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateStatus;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function assignComplianceStatus(EducationCandidate $candidate, Industry $industry, string $companyId, string $statusName, ?string $createdAt = null): void
{
    $status = CandidateStatus::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industry->id,
        'name' => $statusName,
    ]);

    $assignment = CandidateCandidateStatus::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);

    if ($createdAt) {
        $assignment->forceFill(['created_at' => $createdAt])->save();
    }
}

function notCompleteTableProperties(): array
{
    return [
        'candidateModelClass' => EducationCandidate::class,
        'vettingResourceClass' => VettingResource::class,
        'stepLabelsList' => [
            'Personal Details', 'Pay Rates', 'Skills', 'Documents', 'Security Checks', 'TRA Checks', 'DBS', 'References', 'Confirm',
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

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('through to live this week only counts candidates who went live in the current week', function () {
    $thisWeekCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignComplianceStatus($thisWeekCandidate, $this->industry, $this->user->company_id, 'Live', Carbon::now()->startOfWeek()->addDay()->toDateTimeString());

    $lastWeekCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignComplianceStatus($lastWeekCandidate, $this->industry, $this->user->company_id, 'Live', Carbon::now()->subWeek()->toDateTimeString());

    $stillVettingCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignComplianceStatus($stillVettingCandidate, $this->industry, $this->user->company_id, 'Vetting');

    $count = Livewire::test(ComplianceEducationKpiOverview::class)->instance()->throughToLiveThisWeekCount();

    expect($count)->toBe(1);
});

test('outstanding in vetting counts candidates currently assigned the Vetting status', function () {
    $vettingCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignComplianceStatus($vettingCandidate, $this->industry, $this->user->company_id, 'Vetting');

    $liveCandidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    assignComplianceStatus($liveCandidate, $this->industry, $this->user->company_id, 'Live');

    $count = Livewire::test(ComplianceEducationKpiOverview::class)->instance()->outstandingInVettingCount();

    expect($count)->toBe(1);
});

test('average days to live averages created_at to compliance_completed_at, since compliance completing is what makes a candidate live', function () {
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'created_at' => Carbon::parse('2026-01-01'),
        'compliance_completed_at' => Carbon::parse('2026-01-11'),
    ]);
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'created_at' => Carbon::parse('2026-01-01'),
        'compliance_completed_at' => Carbon::parse('2026-01-06'),
    ]);
    EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'compliance_completed_at' => null,
    ]);

    $average = Livewire::test(ComplianceEducationKpiOverview::class)->instance()->averageDaysToLive();

    expect($average)->toBe(7.5);
});

test('average days to live is null when no candidate has completed compliance', function () {
    EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_completed_at' => null]);

    $average = Livewire::test(ComplianceEducationKpiOverview::class)->instance()->averageDaysToLive();

    expect($average)->toBeNull();
});

test('average days from compliance completion to live averages the application completed_at to compliance_completed_at', function () {
    $candidateA = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'compliance_completed_at' => Carbon::parse('2026-01-11'),
    ]);
    $candidateA->application()->create([
        'email' => $candidateA->email,
        'status' => 'completed',
        'token' => 'token-a',
        'expires_on' => now()->addDays(7),
        'completed_at' => Carbon::parse('2026-01-01'),
    ]);

    $candidateB = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'compliance_completed_at' => Carbon::parse('2026-01-06'),
    ]);
    $candidateB->application()->create([
        'email' => $candidateB->email,
        'status' => 'completed',
        'token' => 'token-b',
        'expires_on' => now()->addDays(7),
        'completed_at' => Carbon::parse('2026-01-01'),
    ]);

    // No application at all, and compliance not yet completed - neither should count.
    EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_completed_at' => null]);

    $average = Livewire::test(ComplianceEducationKpiOverview::class)->instance()->averageDaysFromComplianceCompletionToLive();

    expect($average)->toBe(7.5);
});

test('average days from compliance completion to live ignores candidates whose application was never completed', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'compliance_completed_at' => Carbon::parse('2026-01-11'),
    ]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'pending',
        'token' => 'token-c',
        'expires_on' => now()->addDays(7),
        'completed_at' => null,
    ]);

    $average = Livewire::test(ComplianceEducationKpiOverview::class)->instance()->averageDaysFromComplianceCompletionToLive();

    expect($average)->toBeNull();
});

test('average days from compliance completion to live is null when nobody has completed compliance', function () {
    EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_completed_at' => null]);

    $average = Livewire::test(ComplianceEducationKpiOverview::class)->instance()->averageDaysFromComplianceCompletionToLive();

    expect($average)->toBeNull();
});

test('the vetting bucket table only shows candidates whose step falls in its range and excludes completed candidates', function () {
    $notComplete = EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 2]);
    assignComplianceStatus($notComplete, $this->industry, $this->user->company_id, 'Vetting');

    $mostlyComplete = EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 5]);
    assignComplianceStatus($mostlyComplete, $this->industry, $this->user->company_id, 'Vetting');

    $completed = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'compliance_step' => 2,
        'compliance_completed_at' => now(),
    ]);
    assignComplianceStatus($completed, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(ComplianceVettingTable::class, notCompleteTableProperties())
        ->assertCanSeeTableRecords([$notComplete])
        ->assertCanNotSeeTableRecords([$mostlyComplete, $completed]);
});

test('the vetting bucket table can be searched by name', function () {
    $match = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'compliance_step' => 1,
        'first_name' => 'Zaphod',
        'last_name' => 'Beeblebrox',
    ]);
    assignComplianceStatus($match, $this->industry, $this->user->company_id, 'Vetting');

    $other = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'compliance_step' => 1,
        'first_name' => 'Arthur',
        'last_name' => 'Dent',
    ]);
    assignComplianceStatus($other, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(ComplianceVettingTable::class, notCompleteTableProperties())
        ->searchTable('Zaphod')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

test('the vetting bucket table can be sorted by step', function () {
    $stepOne = EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 1]);
    assignComplianceStatus($stepOne, $this->industry, $this->user->company_id, 'Vetting');

    $stepThree = EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 3]);
    assignComplianceStatus($stepThree, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(ComplianceVettingTable::class, notCompleteTableProperties())
        ->sortTable('compliance_step')
        ->assertCanSeeTableRecords([$stepOne, $stepThree], inOrder: true)
        ->sortTable('compliance_step', 'desc')
        ->assertCanSeeTableRecords([$stepThree, $stepOne], inOrder: true);
});

test('the name column links to the vetting wizard for the candidate', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 1]);
    assignComplianceStatus($candidate, $this->industry, $this->user->company_id, 'Vetting');

    $url = Livewire::test(ComplianceVettingTable::class, notCompleteTableProperties())
        ->instance()
        ->vettingUrl($candidate);

    expect($url)->toBe(VettingResource::getUrl('edit', ['record' => $candidate]));
});

test('the step column describes the current step out of the total', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 3]);

    $label = Livewire::test(ComplianceVettingTable::class, notCompleteTableProperties())
        ->instance()
        ->stepLabel($candidate);

    expect($label)->toBe('Step 3 of 9: Skills');
});

test('the table heading is rendered as a badge in the buckets colour, matching the compliance tab', function () {
    Livewire::test(ComplianceVettingTable::class, notCompleteTableProperties())
        ->assertSeeHtml('fi-color-danger')
        ->assertSeeHtml('Not Complete');
});

test('the table heading includes a count of how many candidates are in that bucket', function () {
    $notComplete = EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 2]);
    assignComplianceStatus($notComplete, $this->industry, $this->user->company_id, 'Vetting');

    $mostlyComplete = EducationCandidate::factory()->create(['company_id' => $this->user->company_id, 'compliance_step' => 5]);
    assignComplianceStatus($mostlyComplete, $this->industry, $this->user->company_id, 'Vetting');

    Livewire::test(ComplianceVettingTable::class, notCompleteTableProperties())
        ->assertSeeHtml('Not Complete (1)');
});

test('vetting buckets divide the total steps into three roughly equal ranges', function () {
    expect(ComplianceVettingTable::buckets(9))->toBe([
        ['from' => 1, 'to' => 3, 'heading' => 'Not Complete', 'color' => 'danger'],
        ['from' => 4, 'to' => 6, 'heading' => 'Mostly Complete', 'color' => 'warning'],
        ['from' => 7, 'to' => 9, 'heading' => 'Almost Complete', 'color' => 'info'],
    ]);
});
