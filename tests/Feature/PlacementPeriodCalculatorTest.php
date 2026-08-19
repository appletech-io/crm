<?php

use App\Models\Client;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
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

    $this->filledStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Filled',
        'is_filled_status' => true,
    ]);

    $this->openStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Open',
        'is_filled_status' => false,
    ]);
});

function createFilledVacancy(User $user, JobStatus $status, JobTitle $jobTitle, string $filledAt, array $attributes = []): Vacancy
{
    $client = Client::factory()->create(['company_id' => $user->company_id, 'industry_id' => $status->industry_id]);

    return Vacancy::factory()->create(array_merge([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $status->id,
        'filled_at' => $filledAt,
        'placement_fee_percentage' => 15,
        'salary_min' => 20000,
        'salary_max' => 30000,
        'positions_available' => 1,
    ], $attributes));
}

test('totals only counts vacancies in a filled-type status within the range', function () {
    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-01-10');
    createFilledVacancy($this->user, $this->openStatus, $this->jobTitle, '2026-01-11');

    $totals = PlacementPeriodCalculator::totals(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($totals['count'])->toBe(1)
        ->and($totals['value'])->toBe(3750.0)
        ->and($totals['avgValue'])->toBe(3750.0);
});

test('vacancies filled outside the range are excluded', function () {
    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-02-05');

    $totals = PlacementPeriodCalculator::totals(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($totals['count'])->toBe(0)
        ->and($totals['value'])->toBe(0.0);
});

test('byWeek buckets placements by the week they were filled', function () {
    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-01-05');
    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-01-06');
    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-01-12');

    $weeks = PlacementPeriodCalculator::byWeek(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($weeks)->toHaveCount(2)
        ->and($weeks[0]['count'])->toBe(2)
        ->and($weeks[1]['count'])->toBe(1);
});

test('byClient groups placement count and value by client', function () {
    $clientA = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $clientB = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-01-05', ['client_id' => $clientA->id]);
    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-01-06', ['client_id' => $clientA->id]);
    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-01-07', ['client_id' => $clientB->id]);

    $rows = PlacementPeriodCalculator::byClient(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'))->keyBy('clientId');

    expect($rows[$clientA->id]['count'])->toBe(2)
        ->and($rows[$clientB->id]['count'])->toBe(1);
});

test('a consultant filter excludes other consultants placements', function () {
    $consultantA = User::factory()->create(['company_id' => $this->company->id]);
    $consultantB = User::factory()->create(['company_id' => $this->company->id]);

    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-01-05', ['consultant_id' => $consultantA->id]);
    createFilledVacancy($this->user, $this->filledStatus, $this->jobTitle, '2026-01-06', ['consultant_id' => $consultantB->id]);

    $totals = PlacementPeriodCalculator::totals(
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
        consultantId: $consultantA->id,
    );

    expect($totals['count'])->toBe(1);
});
