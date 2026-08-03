<?php

use App\Filament\Resources\Vacancies\Pages\CreateVacancy;
use App\Filament\Resources\Vacancies\Pages\ListVacancies;
use App\Models\CandidateSkill;
use App\Models\Client;
use App\Models\Company;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->jobStatus = JobStatus::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
});

test('list page renders', function () {
    Livewire::test(ListVacancies::class)->assertSuccessful();
});

test('can create a vacancy for a client with the required fields', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Year 3 Class Teacher',
            'salary_min' => 25000,
            'salary_max' => 32000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'Year 3 Class Teacher')->first();

    expect($vacancy)->not->toBeNull()
        ->and($vacancy->company_id)->toBe($this->company->id)
        ->and($vacancy->client_id)->toBe($this->client->id)
        ->and($vacancy->job_title_id)->toBe($this->jobTitle->id)
        ->and($vacancy->salary_min)->toBe(25000.0)
        ->and($vacancy->salary_max)->toBe(32000.0)
        ->and($vacancy->positions_available)->toBe(1)
        ->and($vacancy->job_status_id)->toBe($this->jobStatus->id);
});

test('creating a vacancy stamps the consultant_id to the creating user', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'SEN Teaching Assistant',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'SEN Teaching Assistant')->first();

    expect($vacancy->consultant_id)->toBe($this->user->id);
});

test('client, job title, status and title are required', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => null,
            'job_title_id' => null,
            'job_status_id' => null,
            'title' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['client_id', 'job_title_id', 'job_status_id', 'title']);
});

test('skills can be attached to a vacancy and persist', function () {
    $skillA = CandidateSkill::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $skillB = CandidateSkill::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Nursery Practitioner',
            'skills' => [$skillA->id, $skillB->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'Nursery Practitioner')->first();

    expect($vacancy->skills()->pluck('candidate_skills.id')->sort()->values()->all())
        ->toBe(collect([$skillA->id, $skillB->id])->sort()->values()->all());
});

test('the create form is prefilled with the client from the query string', function () {
    Livewire::withQueryParams(['client_id' => $this->client->id])
        ->test(CreateVacancy::class)
        ->assertFormSet(['client_id' => $this->client->id]);
});

test('a non-admin only sees their own vacancies, admin sees all', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');

    $ownVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $consultant->id,
    ]);

    $othersVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $this->user->id,
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListVacancies::class)
        ->assertCanSeeTableRecords([$ownVacancy])
        ->assertCanNotSeeTableRecords([$othersVacancy]);

    $this->actingAs($this->user);

    Livewire::test(ListVacancies::class)
        ->assertCanSeeTableRecords([$ownVacancy, $othersVacancy]);
});

test('a vacancy for a client in a different industry is not visible', function () {
    $otherIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    $otherIndustryClient = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $otherIndustry->id,
    ]);

    $otherIndustryVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $otherIndustryClient->id,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $this->user->id,
    ]);

    $mine = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $this->user->id,
    ]);

    Livewire::test(ListVacancies::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$otherIndustryVacancy]);
});
