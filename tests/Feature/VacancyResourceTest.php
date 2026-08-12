<?php

use App\Filament\Resources\Vacancies\Pages\CreateVacancy;
use App\Filament\Resources\Vacancies\Pages\EditVacancy;
use App\Filament\Resources\Vacancies\Pages\ListVacancies;
use App\Filament\Widgets\VacancyApplicantsTable;
use App\Filament\Widgets\VacancyMatchesTable;
use App\Jobs\MatchCandidatesToVacancy;
use App\Models\CandidateSkill;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyCandidateMatch;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Bus;
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

test('the placement fee percentage saves on create and can be updated on edit', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Year 3 Class Teacher',
            'placement_fee_percentage' => 15,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'Year 3 Class Teacher')->first();
    expect($vacancy->placement_fee_percentage)->toBe(15.0);

    Livewire::test(EditVacancy::class, ['record' => $vacancy->getRouteKey()])
        ->fillForm(['placement_fee_percentage' => 20])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vacancy->refresh()->placement_fee_percentage)->toBe(20.0);
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

test('creating a vacancy generates a unique slug from the title, and it never changes on later edits', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Year 3 Class Teacher',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'Year 3 Class Teacher')->first();

    expect($vacancy->slug)->toBe('year-3-class-teacher');

    Livewire::test(EditVacancy::class, ['record' => $vacancy->getRouteKey()])
        ->fillForm(['title' => 'Year 4 Class Teacher'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vacancy->refresh()->slug)->toBe('year-3-class-teacher');
});

test('the edit page shows a copyable public application link built from the slug', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
        'slug' => 'year-3-class-teacher',
    ]);

    Livewire::test(EditVacancy::class, ['record' => $vacancy->getRouteKey()])
        ->assertFormFieldExists('apply_url', function (TextInput $field) use ($vacancy): bool {
            return $field->getState() === route('vacancy.apply', $vacancy->slug);
        });
});

test('creating a vacancy with the match toggle on dispatches the matching job', function () {
    Bus::fake();

    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Year 3 Class Teacher',
            'run_match' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'Year 3 Class Teacher')->first();

    Bus::assertDispatched(MatchCandidatesToVacancy::class, fn (MatchCandidatesToVacancy $job): bool => $job->vacancyId === $vacancy->id);
});

test('creating a vacancy with the match toggle off does not dispatch the matching job', function () {
    Bus::fake();

    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Year 3 Class Teacher',
            'run_match' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Bus::assertNotDispatched(MatchCandidatesToVacancy::class);
});

test('the run match header action on the edit page dispatches the matching job', function () {
    Bus::fake();

    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    Livewire::test(EditVacancy::class, ['record' => $vacancy->getRouteKey()])
        ->callAction('runMatch');

    Bus::assertDispatched(MatchCandidatesToVacancy::class, fn (MatchCandidatesToVacancy $job): bool => $job->vacancyId === $vacancy->id);
});

test('the matches widget lists ranked matches with a link to the candidate profile', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $matchedCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $matchedCandidate->id,
        'score' => 82,
    ]);

    Livewire::test(VacancyMatchesTable::class, ['record' => $vacancy])
        ->assertSee('Jane Doe')
        ->assertSee('82%');
});

test('the matches widget shows an empty state when nothing has been matched yet', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    Livewire::test(VacancyMatchesTable::class, ['record' => $vacancy])
        ->assertSee('No matches yet');
});

test('the applicants widget lists candidates who applied to the vacancy, with a link to their profile', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $applicant = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@example.com',
    ]);

    VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
    ]);

    $otherVacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);
    $otherApplicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $otherApplication = VacancyApplication::create([
        'vacancy_id' => $otherVacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $otherApplicant->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->assertCanSeeTableRecords([VacancyApplication::where('candidate_id', $applicant->id)->first()])
        ->assertCanNotSeeTableRecords([$otherApplication])
        ->assertSee('Jane Doe')
        ->assertSee('jane.doe@example.com');
});

test('the applicants widget shows an empty state when nobody has applied yet', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->assertSee('No applicants yet');
});

test('an applicant can be shortlisted and un-shortlisted, logging an activity on the vacancy each time', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $applicant = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
    ]);

    expect($application->isShortlisted())->toBeFalse();

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->callTableAction('toggleShortlist', $application);

    expect($application->refresh()->isShortlisted())->toBeTrue();
    expect($vacancy->activities()->where('note', 'Shortlisted: Jane Doe')->exists())->toBeTrue();

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->callTableAction('toggleShortlist', $application);

    expect($application->refresh()->isShortlisted())->toBeFalse();
    expect($vacancy->activities()->where('note', 'Removed from shortlist: Jane Doe')->exists())->toBeTrue();
});

test('the shortlisted filter narrows the applicants list to only shortlisted candidates', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $shortlistedApplicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $shortlistedApplication = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $shortlistedApplicant->id,
        'shortlisted_at' => now(),
    ]);

    $otherApplicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $otherApplication = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $otherApplicant->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->filterTable('shortlisted_at', true)
        ->assertCanSeeTableRecords([$shortlistedApplication])
        ->assertCanNotSeeTableRecords([$otherApplication]);
});

test('the shortlisted tab only ever shows shortlisted candidates, with no filter needed', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $shortlistedApplicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $shortlistedApplication = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $shortlistedApplicant->id,
        'shortlisted_at' => now(),
    ]);

    $otherApplicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $otherApplication = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $otherApplicant->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy, 'onlyShortlisted' => true])
        ->assertCanSeeTableRecords([$shortlistedApplication])
        ->assertCanNotSeeTableRecords([$otherApplication])
        ->assertDontSee('Not shortlisted');
});

test('the shortlisted tab shows its own empty state when nobody has been shortlisted yet', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $applicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy, 'onlyShortlisted' => true])
        ->assertSee('No candidates shortlisted yet');
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
