<?php

use App\Enums\VacancyEmploymentType;
use App\Models\CandidateStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobStatusAutomation;
use App\Models\JobTitle;
use App\Models\Vacancy;
use App\Models\VacancyPlacement;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create();

    $this->openStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'is_filled_status' => false,
    ]);

    $this->filledStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'is_filled_status' => true,
    ]);

    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
});

function createPlacementVacancy(array $overrides = []): Vacancy
{
    return Vacancy::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'client_id' => test()->client->id,
        'job_title_id' => test()->jobTitle->id,
        'job_status_id' => test()->openStatus->id,
        'placement_fee_percentage' => 15,
        'positions_available' => 1,
    ], $overrides));
}

test('actualPlacementValue sums actual salaries times the fee percentage', function () {
    $vacancy = createPlacementVacancy(['positions_available' => 2]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'actual_salary' => 20000,
    ]);
    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'actual_salary' => 30000,
    ]);

    expect($vacancy->actualPlacementValue())->toBe(7500.0);
});

test('actualPlacementValue is null for a temp vacancy', function () {
    $vacancy = createPlacementVacancy(['employment_type' => VacancyEmploymentType::Temp->value]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'actual_salary' => 20000,
    ]);

    expect($vacancy->actualPlacementValue())->toBeNull();
});

test('actualPlacementValue is null without a placement fee percentage', function () {
    $vacancy = createPlacementVacancy(['placement_fee_percentage' => null]);

    VacancyPlacement::factory()->create(['vacancy_id' => $vacancy->id, 'actual_salary' => 20000]);

    expect($vacancy->actualPlacementValue())->toBeNull();
});

test('isFullyPlaced is true once placements meet positions available', function () {
    $vacancy = createPlacementVacancy(['positions_available' => 2]);

    expect($vacancy->isFullyPlaced())->toBeFalse();

    VacancyPlacement::factory()->create(['vacancy_id' => $vacancy->id]);
    expect($vacancy->isFullyPlaced())->toBeFalse();

    VacancyPlacement::factory()->create(['vacancy_id' => $vacancy->id]);
    expect($vacancy->isFullyPlaced())->toBeTrue();
});

test('placementsFilled is false without any placements', function () {
    $vacancy = createPlacementVacancy();

    expect($vacancy->placements_filled)->toBeFalse();
});

test('placementsFilled is false when placed but the candidate status is not a filled one', function () {
    $vacancy = createPlacementVacancy();
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
    ]);

    $otherStatus = CandidateStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'is_filled_status' => false,
    ]);
    $candidate->statuses()->create(['candidate_status_id' => $otherStatus->id]);

    expect($vacancy->fresh()->placements_filled)->toBeFalse();
});

test('placementsFilled is false when only some positions are placed, even if that candidate is filled', function () {
    $vacancy = createPlacementVacancy(['positions_available' => 2]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
    ]);

    $placedStatus = CandidateStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'is_filled_status' => true,
    ]);
    $candidate->statuses()->create(['candidate_status_id' => $placedStatus->id]);

    expect($vacancy->fresh()->placements_filled)->toBeFalse();
});

test('placementsFilled is true once every position is placed and every placed candidate is filled', function () {
    $vacancy = createPlacementVacancy(['positions_available' => 2]);
    $placedStatus = CandidateStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'is_filled_status' => true,
    ]);

    $first = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $first->id,
    ]);
    $first->statuses()->create(['candidate_status_id' => $placedStatus->id]);

    $second = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $second->id,
    ]);
    $second->statuses()->create(['candidate_status_id' => $placedStatus->id]);

    expect($vacancy->fresh()->placements_filled)->toBeTrue();
});

test('a candidate reaching a filled status triggers a configured job status automation off placements_filled', function () {
    $vacancy = createPlacementVacancy();
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
    ]);

    JobStatusAutomation::create([
        'job_status_id' => $this->openStatus->id,
        'to_job_status_id' => $this->filledStatus->id,
        'conditions' => [['field' => 'placements_filled', 'operator' => 'equals', 'value' => '1']],
    ]);

    $placedStatus = CandidateStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'is_filled_status' => true,
    ]);

    $candidate->statuses()->create(['candidate_status_id' => $placedStatus->id]);

    expect($vacancy->fresh()->job_status_id)->toBe($this->filledStatus->id);
    expect($vacancy->fresh()->filled_at)->not->toBeNull();
});

test('placing an already-filled candidate also triggers the automation', function () {
    $vacancy = createPlacementVacancy();
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $placedStatus = CandidateStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'is_filled_status' => true,
    ]);
    $candidate->statuses()->create(['candidate_status_id' => $placedStatus->id]);

    JobStatusAutomation::create([
        'job_status_id' => $this->openStatus->id,
        'to_job_status_id' => $this->filledStatus->id,
        'conditions' => [['field' => 'placements_filled', 'operator' => 'equals', 'value' => '1']],
    ]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
    ]);

    expect($vacancy->fresh()->job_status_id)->toBe($this->filledStatus->id);
});

test('a candidate reaching a filled status does not move the vacancy without a matching automation configured', function () {
    $vacancy = createPlacementVacancy();
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
    ]);

    $placedStatus = CandidateStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'is_filled_status' => true,
    ]);

    $candidate->statuses()->create(['candidate_status_id' => $placedStatus->id]);

    expect($vacancy->fresh()->job_status_id)->toBe($this->openStatus->id);
});

test('the automation does not fire when not every position is placed', function () {
    $vacancy = createPlacementVacancy(['positions_available' => 2]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
    ]);

    JobStatusAutomation::create([
        'job_status_id' => $this->openStatus->id,
        'to_job_status_id' => $this->filledStatus->id,
        'conditions' => [['field' => 'placements_filled', 'operator' => 'equals', 'value' => '1']],
    ]);

    $placedStatus = CandidateStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'is_filled_status' => true,
    ]);

    $candidate->statuses()->create(['candidate_status_id' => $placedStatus->id]);

    expect($vacancy->fresh()->job_status_id)->toBe($this->openStatus->id);
});
