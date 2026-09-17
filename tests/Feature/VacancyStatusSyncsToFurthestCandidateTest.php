<?php

use App\Models\Candidate;
use App\Models\Client;
use App\Models\Company;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'it']);
    $this->company->industries()->attach($this->industry->id, ['uses_bookings' => false]);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->industries()->attach($this->industry);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    // Created in pipeline order, so factory sort_order ends up ascending to match.
    $this->open = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'Open']);
    $this->interview1 = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'Interview Stage 1']);
    $this->interview2 = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'Interview Stage 2+']);
    $this->placed = JobStatus::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'Placed', 'is_filled_status' => true]);

    $this->vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->open->id,
    ]);
});

function makeCandidate(): Candidate
{
    return Candidate::factory()->create(['company_id' => test()->company->id, 'industry_id' => test()->industry->id]);
}

test('the vacancy status moves to match a candidate who reaches a later stage', function () {
    VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => makeCandidate()->id,
        'job_status_id' => $this->interview1->id,
    ]);

    expect($this->vacancy->fresh()->job_status_id)->toBe($this->interview1->id);
});

test('a second, earlier-stage candidate does not pull the vacancy status backward', function () {
    VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => makeCandidate()->id,
        'job_status_id' => $this->interview2->id,
    ]);

    VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => makeCandidate()->id,
        'job_status_id' => $this->open->id,
    ]);

    expect($this->vacancy->fresh()->job_status_id)->toBe($this->interview2->id);
});

test('moving a candidate further along updates the vacancy status again', function () {
    $application = VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => makeCandidate()->id,
        'job_status_id' => $this->interview1->id,
    ]);

    expect($this->vacancy->fresh()->job_status_id)->toBe($this->interview1->id);

    $application->update(['job_status_id' => $this->placed->id]);

    expect($this->vacancy->fresh()->job_status_id)->toBe($this->placed->id);
});

test('deleting the furthest-along candidate falls back to the next-furthest remaining one', function () {
    $furthest = VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => makeCandidate()->id,
        'job_status_id' => $this->placed->id,
    ]);

    VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => makeCandidate()->id,
        'job_status_id' => $this->interview1->id,
    ]);

    expect($this->vacancy->fresh()->job_status_id)->toBe($this->placed->id);

    $furthest->delete();

    expect($this->vacancy->fresh()->job_status_id)->toBe($this->interview1->id);
});

test('a vacancy with no applications keeps whatever status it was given', function () {
    expect($this->vacancy->fresh()->job_status_id)->toBe($this->open->id);
});

test('a different vacancy is never affected by another vacancy\'s applications', function () {
    $otherVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->open->id,
    ]);

    VacancyApplication::create([
        'vacancy_id' => $this->vacancy->id,
        'candidate_type' => Candidate::class,
        'candidate_id' => makeCandidate()->id,
        'job_status_id' => $this->placed->id,
    ]);

    expect($otherVacancy->fresh()->job_status_id)->toBe($this->open->id);
});
