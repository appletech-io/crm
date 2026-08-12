<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\Vacancy;

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

    $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
});

function createVacancyForFilledAtTest(Client $client, JobTitle $jobTitle, JobStatus $status): Vacancy
{
    return Vacancy::factory()->create([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $status->id,
    ]);
}

test('moving a vacancy into a filled-type status sets filled_at', function () {
    $vacancy = createVacancyForFilledAtTest($this->client, $this->jobTitle, $this->openStatus);

    expect($vacancy->filled_at)->toBeNull();

    $vacancy->update(['job_status_id' => $this->filledStatus->id]);

    expect($vacancy->fresh()->filled_at)->not->toBeNull();
});

test('moving a vacancy out of a filled-type status clears filled_at', function () {
    $vacancy = createVacancyForFilledAtTest($this->client, $this->jobTitle, $this->openStatus);
    $vacancy->update(['job_status_id' => $this->filledStatus->id]);

    expect($vacancy->fresh()->filled_at)->not->toBeNull();

    $vacancy->update(['job_status_id' => $this->openStatus->id]);

    expect($vacancy->fresh()->filled_at)->toBeNull();
});

test('an unrelated update does not clobber an already-set filled_at', function () {
    $vacancy = createVacancyForFilledAtTest($this->client, $this->jobTitle, $this->openStatus);
    $vacancy->update(['job_status_id' => $this->filledStatus->id]);

    $filledAt = $vacancy->fresh()->filled_at;

    $vacancy->update(['title' => 'A different title']);

    expect($vacancy->fresh()->filled_at->eq($filledAt))->toBeTrue();
});

test('a vacancy already in a filled-type status is not touched by an unrelated update', function () {
    $vacancy = createVacancyForFilledAtTest($this->client, $this->jobTitle, $this->filledStatus);

    expect($vacancy->fresh()->filled_at)->toBeNull();

    $vacancy->update(['title' => 'Renamed while already filled']);

    expect($vacancy->fresh()->filled_at)->toBeNull();
});
