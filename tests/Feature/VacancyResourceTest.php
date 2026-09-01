<?php

use App\Enums\VacancyEmploymentType;
use App\Filament\Resources\Bookings\BookingResource;
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
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyCandidateMatch;
use App\Models\VacancyPlacement;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

test('a matched candidate can be added to the shortlist from the matches widget', function () {
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

    $match = VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $matchedCandidate->id,
        'score' => 82,
    ]);

    Livewire::test(VacancyMatchesTable::class, ['record' => $vacancy])
        ->callTableAction('addToShortlist', $match);

    $application = VacancyApplication::where('vacancy_id', $vacancy->id)
        ->where('candidate_id', $matchedCandidate->id)
        ->first();

    expect($application)->not->toBeNull()
        ->and($application->isShortlisted())->toBeTrue()
        ->and($application->match_strength)->toBe(82);

    expect($vacancy->activities()->where('note', 'Shortlisted: Jane Doe')->exists())->toBeTrue();

    Livewire::test(VacancyMatchesTable::class, ['record' => $vacancy])
        ->assertTableActionHidden('addToShortlist', $match);
});

test('shortlisting a match who already applied shortlists their existing application instead of duplicating it', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
    ]);

    $match = VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'score' => 90,
    ]);

    Livewire::test(VacancyMatchesTable::class, ['record' => $vacancy])
        ->callTableAction('addToShortlist', $match);

    expect(VacancyApplication::where('vacancy_id', $vacancy->id)->where('candidate_id', $candidate->id)->count())->toBe(1);
    expect($application->refresh()->isShortlisted())->toBeTrue();
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

test('client is required for a permanent vacancy but not for a temp vacancy', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => null,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Year 3 Class Teacher',
            'employment_type' => VacancyEmploymentType::Permanent->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['client_id']);

    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => null,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'General Supply Cover',
            'employment_type' => VacancyEmploymentType::Temp->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'General Supply Cover')->first();

    expect($vacancy->client_id)->toBeNull()
        ->and($vacancy->industry_id)->toBe($this->industry->id);
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

test('a client-less temp vacancy still shows up in its own industry\'s list', function () {
    $generalCover = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => null,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $this->user->id,
        'employment_type' => VacancyEmploymentType::Temp,
    ]);

    Livewire::test(ListVacancies::class)
        ->assertCanSeeTableRecords([$generalCover]);
});

test('a vacancy defaults to permanent employment type', function () {
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

    expect($vacancy->employment_type)->toBe(VacancyEmploymentType::Permanent);
});

test('a vacancy can be created as a temp role', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Supply Teacher',
            'employment_type' => VacancyEmploymentType::Temp->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'Supply Teacher')->first();

    expect($vacancy->employment_type)->toBe(VacancyEmploymentType::Temp);
});

test('a shortlisted applicant can be marked as placed from the applicants widget', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
        'placement_fee_percentage' => 15,
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

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->callTableAction('markPlaced', $application, data: ['actual_salary' => 28000]);

    $placement = VacancyPlacement::where('vacancy_id', $vacancy->id)
        ->where('candidate_id', $applicant->id)
        ->first();

    expect($placement)->not->toBeNull()
        ->and($placement->actual_salary)->toBe(28000.0);
    expect($vacancy->activities()->where('note', 'Marked as placed: Jane Doe')->exists())->toBeTrue();
});

test('the mark as placed action is hidden for temp vacancies', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
        'employment_type' => VacancyEmploymentType::Temp->value,
    ]);

    $applicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->assertTableActionHidden('markPlaced', $application);
});

test('a matched candidate can be marked as placed from the matches widget', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
        'placement_fee_percentage' => 15,
    ]);

    $matchedCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $match = VacancyCandidateMatch::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $matchedCandidate->id,
        'score' => 82,
    ]);

    Livewire::test(VacancyMatchesTable::class, ['record' => $vacancy])
        ->callTableAction('markPlaced', $match, data: ['actual_salary' => 30000]);

    $placement = VacancyPlacement::where('vacancy_id', $vacancy->id)
        ->where('candidate_id', $matchedCandidate->id)
        ->first();

    expect($placement)->not->toBeNull()
        ->and($placement->actual_salary)->toBe(30000.0);
});

test('the send application form action is hidden for an applicant who is not shortlisted', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $applicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->assertTableActionHidden('sendApplicationForm', $application);
});

test('the send application form action is visible for a shortlisted applicant with no application yet', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $applicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
        'shortlisted_at' => now(),
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->assertTableActionVisible('sendApplicationForm', $application);
});

test('the send application form action is hidden once the candidate already has an application', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $applicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $applicant->application()->create([
        'email' => $applicant->email,
        'status' => 'pending',
        'token' => Str::uuid(),
        'expires_on' => now()->addWeeks(2)->toDateString(),
    ]);

    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
        'shortlisted_at' => now(),
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->assertTableActionHidden('sendApplicationForm', $application);
});

test('clicking send application form on a shortlisted applicant creates an application and sends the email immediately', function () {
    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Application',
        'type' => 'application',
        'subject' => 'Apply now, {firstname}',
        'body' => 'Hi {firstname}, please apply: {application_link}',
    ]);

    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $applicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
        'shortlisted_at' => now(),
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->callTableAction('sendApplicationForm', $application)
        ->assertNotified('Application form sent');

    expect($applicant->application()->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMail'));
});

test('the cover date fields are hidden for a permanent vacancy and visible for a temp one', function () {
    Livewire::test(CreateVacancy::class)
        ->assertFormFieldIsHidden('start_date')
        ->assertFormFieldIsHidden('end_date')
        ->assertFormFieldIsVisible('placement_fee_percentage')
        ->set('data.employment_type', VacancyEmploymentType::Temp->value)
        ->assertFormFieldIsVisible('start_date')
        ->assertFormFieldIsVisible('end_date')
        ->assertFormFieldIsHidden('placement_fee_percentage');
});

test('the location field is only visible when no client is selected', function () {
    Livewire::test(CreateVacancy::class)
        ->assertFormFieldIsVisible('location')
        ->set('data.client_id', $this->client->id)
        ->assertFormFieldIsHidden('location')
        ->set('data.client_id', null)
        ->assertFormFieldIsVisible('location');
});

test('a client-less temp vacancy persists its location', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => null,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'General Supply Cover',
            'employment_type' => VacancyEmploymentType::Temp->value,
            'location' => 'Birmingham',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'General Supply Cover')->first();

    expect($vacancy->location)->toBe('Birmingham');
});

test('a temp vacancy persists its cover start and end dates', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Supply Teacher',
            'employment_type' => VacancyEmploymentType::Temp->value,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'Supply Teacher')->first();

    expect($vacancy->start_date->toDateString())->toBe('2026-09-01')
        ->and($vacancy->end_date->toDateString())->toBe('2026-09-05');
});

test('the salary fields are hidden for a temp vacancy and the day rate fields are hidden for a permanent one', function () {
    Livewire::test(CreateVacancy::class)
        ->assertFormFieldIsVisible('salary_min')
        ->assertFormFieldIsVisible('salary_max')
        ->assertFormFieldIsHidden('day_rate_min')
        ->assertFormFieldIsHidden('day_rate_max')
        ->set('data.employment_type', VacancyEmploymentType::Temp->value)
        ->assertFormFieldIsHidden('salary_min')
        ->assertFormFieldIsHidden('salary_max')
        ->assertFormFieldIsVisible('day_rate_min')
        ->assertFormFieldIsVisible('day_rate_max');
});

test('switching a vacancy to temp clears any salary already entered, and switching back clears the day rate', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm(['salary_min' => 25000, 'salary_max' => 35000])
        ->set('data.employment_type', VacancyEmploymentType::Temp->value)
        ->assertFormSet(['salary_min' => null, 'salary_max' => null])
        ->fillForm(['day_rate_min' => 150, 'day_rate_max' => 200])
        ->set('data.employment_type', VacancyEmploymentType::Permanent->value)
        ->assertFormSet(['day_rate_min' => null, 'day_rate_max' => null]);
});

test('a temp vacancy persists its day rate range', function () {
    Livewire::test(CreateVacancy::class)
        ->fillForm([
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'job_status_id' => $this->jobStatus->id,
            'title' => 'Supply Teacher',
            'employment_type' => VacancyEmploymentType::Temp->value,
            'day_rate_min' => 150,
            'day_rate_max' => 200,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $vacancy = Vacancy::where('title', 'Supply Teacher')->first();

    expect($vacancy->day_rate_min)->toBe(150.0)
        ->and($vacancy->day_rate_max)->toBe(200.0)
        ->and($vacancy->salary_min)->toBeNull()
        ->and($vacancy->salary_max)->toBeNull();
});

test('the create booking action is hidden for a permanent vacancy even if the applicant is shortlisted', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
    ]);

    $applicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
        'shortlisted_at' => now(),
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->assertTableActionHidden('createBooking', $application);
});

test('the create booking action is hidden for a temp vacancy applicant who is not shortlisted', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
        'employment_type' => VacancyEmploymentType::Temp->value,
    ]);

    $applicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->assertTableActionHidden('createBooking', $application);
});

test('the create booking action is visible for a shortlisted applicant on a temp vacancy and links to a prefilled create booking url', function () {
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'job_title_id' => $this->jobTitle->id,
        'job_status_id' => $this->jobStatus->id,
        'employment_type' => VacancyEmploymentType::Temp->value,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
    ]);

    $applicant = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $application = VacancyApplication::create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $applicant->id,
        'shortlisted_at' => now(),
    ]);

    Livewire::test(VacancyApplicantsTable::class, ['record' => $vacancy])
        ->assertTableActionVisible('createBooking', $application)
        ->assertTableActionHasUrl('createBooking', BookingResource::getUrl('create', [
            'candidate_id' => $applicant->id,
            'client_id' => $this->client->id,
            'job_title_id' => $this->jobTitle->id,
            'dates' => ['2026-09-01', '2026-09-02', '2026-09-03'],
        ]), record: $application);
});
