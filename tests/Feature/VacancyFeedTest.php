<?php

use App\Enums\VacancyEmploymentType;
use App\Models\Client;
use App\Models\Company;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'county' => 'West Midlands',
        'city' => 'Cradley Heath',
    ]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id, 'name' => 'Teaching Assistant']);
    $this->jobStatus = JobStatus::factory()->create(['company_id' => $this->company->id]);
    $this->consultant = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Kirsty Greaves', 'email' => 'kirsty@applebough.co.uk']);
});

function createFeedVacancy(array $overrides = []): Vacancy
{
    return Vacancy::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'client_id' => test()->client->id,
        'job_title_id' => test()->jobTitle->id,
        'job_status_id' => test()->jobStatus->id,
        'consultant_id' => test()->consultant->id,
        'open_for_applications' => true,
    ], $overrides));
}

test('it lists a temp vacancy with its day rate, category, and reference number', function () {
    $vacancy = createFeedVacancy([
        'title' => 'L2/3 SEN Teaching Assistant',
        'description' => 'A great role.',
        'employment_type' => VacancyEmploymentType::Temp->value,
        'salary_min' => null,
        'salary_max' => null,
        'day_rate_min' => 90,
        'day_rate_max' => 105,
        'start_date' => '2026-08-07',
        'end_date' => '2026-08-26',
        'listing_expires_at' => '2026-08-26',
    ]);

    $response = $this->getJson('/api/vacancies')->assertOk();

    $response->assertJson([
        'data' => [
            [
                'consultant_name' => 'Kirsty Greaves',
                'email' => 'kirsty@applebough.co.uk',
                'job_id' => (string) $vacancy->id,
                'category' => 'Teaching Assistant',
                'type' => 'Contract',
                'startdate' => '07-08-2026',
                'expiry' => '26-08-2026',
                'featured' => null,
                'refno' => "KG-{$vacancy->id}",
                'title' => 'L2/3 SEN Teaching Assistant',
                'summary' => '',
                'description' => 'A great role.',
                'county' => 'West Midlands',
                'town' => 'Cradley Heath',
                'salary_min' => '90',
                'salary_max' => '105',
                'salary_term' => 'Day',
                'keywords' => '',
                'apply' => route('vacancy.apply', $vacancy->slug),
            ],
        ],
    ]);

    $response->assertJsonFragment(['benefits' => "Your own dedicated consultant\r\nA variety of daily and long term positions to suit your needs\r\nCompetitive rates of pay\r\n24/7 access to your dedicated consultant via phone\r\nMinimal administration (no time sheets)\r\nEmail and SMS verification of bookings\r\nOnline diary of bookings, school directions\r\nReferral scheme"]);
});

test('it lists a permanent vacancy with its salary instead of a day rate', function () {
    $vacancy = createFeedVacancy([
        'employment_type' => VacancyEmploymentType::Permanent->value,
        'salary_min' => 25000,
        'salary_max' => 30000,
        'day_rate_min' => null,
        'day_rate_max' => null,
    ]);

    $this->getJson('/api/vacancies')->assertOk()->assertJson([
        'data' => [
            [
                'job_id' => (string) $vacancy->id,
                'type' => 'Permanent',
                'startdate' => null,
                'salary_min' => '25000',
                'salary_max' => '30000',
                'salary_term' => 'Year',
            ],
        ],
    ]);
});

test('a vacancy with no consultant falls back to a generic reference number and null contact details', function () {
    $vacancy = createFeedVacancy(['consultant_id' => null]);

    $this->getJson('/api/vacancies')->assertOk()->assertJson([
        'data' => [
            [
                'consultant_name' => null,
                'email' => null,
                'refno' => "JOB-{$vacancy->id}",
            ],
        ],
    ]);
});

test('a vacancy closed for applications does not appear in the feed', function () {
    createFeedVacancy(['open_for_applications' => false]);

    $this->getJson('/api/vacancies')->assertOk()->assertJsonCount(0, 'data');
});

test('a vacancy past its listing expiry does not appear in the feed', function () {
    createFeedVacancy(['listing_expires_at' => now()->subDay()->toDateString()]);

    $this->getJson('/api/vacancies')->assertOk()->assertJsonCount(0, 'data');
});

test('a vacancy with no listing expiry set stays listed indefinitely', function () {
    createFeedVacancy(['listing_expires_at' => null]);

    $this->getJson('/api/vacancies')->assertOk()->assertJsonCount(1, 'data');
});

test('a vacancy expiring today still appears in the feed', function () {
    createFeedVacancy(['listing_expires_at' => now()->toDateString()]);

    $this->getJson('/api/vacancies')->assertOk()->assertJsonCount(1, 'data');
});
