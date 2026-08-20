<?php

use App\Enums\VacancyEmploymentType;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyPlacement;
use App\Services\Reporting\PlacementPeriodCalculator;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->company = $this->user->company;
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);

    $this->openStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Open',
        'is_filled_status' => false,
    ]);
});

function createPlacement(User $user, JobTitle $jobTitle, JobStatus $status, string $placedAt, array $vacancyAttributes = [], array $placementAttributes = []): VacancyPlacement
{
    $client = Client::factory()->create(['company_id' => $user->company_id, 'industry_id' => $status->industry_id]);

    $vacancy = Vacancy::factory()->create(array_merge([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $status->id,
        'placement_fee_percentage' => 15,
        'positions_available' => 1,
        'employment_type' => VacancyEmploymentType::Permanent->value,
    ], $vacancyAttributes));

    return VacancyPlacement::factory()->create(array_merge([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => EducationCandidate::factory()->create(['company_id' => $user->company_id])->id,
        'actual_salary' => 25000,
        'placed_at' => $placedAt,
    ], $placementAttributes));
}

test('totals only counts permanent placements within the range', function () {
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-10');
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-02-01');

    $totals = PlacementPeriodCalculator::totals(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($totals['count'])->toBe(1)
        ->and($totals['value'])->toBe(3750.0)
        ->and($totals['avgValue'])->toBe(3750.0);
});

test('placements outside the range are excluded', function () {
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-02-05');

    $totals = PlacementPeriodCalculator::totals(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($totals['count'])->toBe(0)
        ->and($totals['value'])->toBe(0.0);
});

test('temp vacancy placements are excluded entirely', function () {
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-10', [
        'employment_type' => VacancyEmploymentType::Temp->value,
    ]);

    $totals = PlacementPeriodCalculator::totals(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($totals['count'])->toBe(0)
        ->and($totals['value'])->toBe(0.0);
});

test('a placement without an actual salary counts but contributes zero value', function () {
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-10', placementAttributes: [
        'actual_salary' => null,
    ]);

    $totals = PlacementPeriodCalculator::totals(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($totals['count'])->toBe(1)
        ->and($totals['value'])->toBe(0.0);
});

test('byWeek buckets placements by the week they were placed', function () {
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-05');
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-06');
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-12');

    $weeks = PlacementPeriodCalculator::byWeek(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($weeks)->toHaveCount(2)
        ->and($weeks[0]['count'])->toBe(2)
        ->and($weeks[1]['count'])->toBe(1);
});

test('a vacancy filled across several weeks shows up incrementally, not as one lump event', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->openStatus->id,
        'placement_fee_percentage' => 15,
        'positions_available' => 2,
        'employment_type' => VacancyEmploymentType::Permanent->value,
    ]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => EducationCandidate::factory()->create(['company_id' => $this->company->id])->id,
        'actual_salary' => 25000,
        'placed_at' => '2026-01-05',
    ]);
    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => EducationCandidate::factory()->create(['company_id' => $this->company->id])->id,
        'actual_salary' => 25000,
        'placed_at' => '2026-01-12',
    ]);

    $weeks = PlacementPeriodCalculator::byWeek(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($weeks)->toHaveCount(2)
        ->and($weeks[0]['count'])->toBe(1)
        ->and($weeks[1]['count'])->toBe(1);
});

test('byClient groups placement count and value by client', function () {
    $clientA = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $clientB = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-05', ['client_id' => $clientA->id]);
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-06', ['client_id' => $clientA->id]);
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-07', ['client_id' => $clientB->id]);

    $rows = PlacementPeriodCalculator::byClient(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'))->keyBy('clientId');

    expect($rows[$clientA->id]['count'])->toBe(2)
        ->and($rows[$clientB->id]['count'])->toBe(1);
});

test('a consultant filter excludes other consultants placements', function () {
    $consultantA = User::factory()->create(['company_id' => $this->company->id]);
    $consultantB = User::factory()->create(['company_id' => $this->company->id]);

    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-05', ['consultant_id' => $consultantA->id]);
    createPlacement($this->user, $this->jobTitle, $this->openStatus, '2026-01-06', ['consultant_id' => $consultantB->id]);

    $totals = PlacementPeriodCalculator::totals(
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
        consultantId: $consultantA->id,
    );

    expect($totals['count'])->toBe(1);
});
