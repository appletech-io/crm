<?php

use App\Ai\Agents\CvParser;
use App\Enums\DocumentType;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\Vacancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function createVacancyFor(string $industrySlug, array $vacancyAttributes = []): Vacancy
{
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => $industrySlug]);
    $client = Client::factory()->create(['company_id' => $company->id, 'industry_id' => $industry->id]);

    return Vacancy::factory()->create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
    ], $vacancyAttributes));
}

test('a closed vacancy shows an applications closed message instead of the form', function () {
    $vacancy = createVacancyFor('education', ['open_for_applications' => false]);

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->assertSuccessful()
        ->assertSee('Applications for this role are now closed.')
        ->assertDontSee('Upload your CV');
});

test('an open vacancy shows the apply form starting at the cv upload step', function () {
    $vacancy = createVacancyFor('education');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->assertSuccessful()
        ->assertSee($vacancy->title)
        ->assertSee($vacancy->client->name)
        ->assertSee('Upload your CV');
});

test('the progress bar renders above the job title, right at the top of the page', function () {
    $vacancy = createVacancyFor('education');

    $html = Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->assertSuccessful()
        ->html();

    $progressPosition = strpos($html, 'Step 1 of');
    $titlePosition = strpos($html, $vacancy->title);

    expect($progressPosition)->not->toBeFalse();
    expect($titlePosition)->not->toBeFalse();
    expect($progressPosition)->toBeLessThan($titlePosition);
});

test('skipping the cv upload advances straight to personal details', function () {
    $vacancy = createVacancyFor('education');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->assertSet('step', 2);
});

test('address defaults to search mode, and manual mode shows the plain fields with the reverse toggle label', function () {
    $vacancy = createVacancyFor('education');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->assertSet('address_manual', false)
        ->assertSee('Enter address manually')
        ->assertDontSee('Search address instead')
        ->set('address_manual', true)
        ->assertSee('Search address instead')
        ->assertDontSee('Start typing an address or postcode');
});

test('address search returns suggestions from the google places autocomplete api', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [
                ['placePrediction' => ['placeId' => 'place-1', 'text' => ['text' => '10 Downing Street, London, UK']]],
                ['placePrediction' => ['placeId' => 'place-2', 'text' => ['text' => '10 Downing Court, Leeds, UK']]],
            ],
        ], 200),
    ]);

    $vacancy = createVacancyFor('education');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('address_search', '10 Downing')
        ->assertSet('address_suggestions', [
            'place-1' => '10 Downing Street, London, UK',
            'place-2' => '10 Downing Court, Leeds, UK',
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'places:autocomplete')
        && $request['input'] === '10 Downing'
        && $request['includedRegionCodes'] === ['gb']);
});

test('address search does not call the api for very short input', function () {
    Http::fake(); // avoid a real network call from the client's own geocoding on creation
    $vacancy = createVacancyFor('education');

    Http::fake(); // reset recorded requests now that setup is done, before the actual assertion

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('address_search', 'SW')
        ->assertSet('address_suggestions', []);

    Http::assertNothingSent();
});

test('selecting an address suggestion populates the address fields from the google places details api', function () {
    Http::fake([
        'places.googleapis.com/v1/places/place-1' => Http::response([
            'formattedAddress' => '10 Downing St, London SW1A 2AA, UK',
            'addressComponents' => [
                ['types' => ['street_number'], 'longText' => '10'],
                ['types' => ['route'], 'longText' => 'Downing Street'],
                ['types' => ['postal_town'], 'longText' => 'London'],
                ['types' => ['administrative_area_level_2'], 'longText' => 'Greater London'],
                ['types' => ['country'], 'longText' => 'United Kingdom'],
                ['types' => ['postal_code'], 'longText' => 'SW1A 2AA'],
            ],
        ], 200),
    ]);

    $vacancy = createVacancyFor('education');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('address_suggestions', ['place-1' => '10 Downing Street, London, UK'])
        ->call('selectAddress', 'place-1')
        ->assertSet('address', '10 Downing Street')
        ->assertSet('city', 'London')
        ->assertSet('county', 'Greater London')
        ->assertSet('country', 'United Kingdom')
        ->assertSet('postcode', 'SW1A 2AA')
        ->assertSet('address_search', '10 Downing St, London SW1A 2AA, UK')
        ->assertSet('address_suggestions', []);
});

test('applying end to end for an education vacancy creates a basic candidate with employment history, skills, and activity logs', function () {
    $vacancy = createVacancyFor('education');
    $companyId = $vacancy->client->company_id;
    $industryId = $vacancy->client->industry_id;

    $onboarding = CandidateStatus::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industryId,
        'name' => 'Onboarding',
    ]);

    $parentSkill = CandidateSkill::factory()->create(['company_id' => $companyId, 'industry_id' => $industryId, 'name' => 'Teaching']);
    $childSkill = CandidateSkill::factory()->create(['company_id' => $companyId, 'industry_id' => $industryId, 'name' => 'Key Stage 2', 'parent_id' => $parentSkill->id]);

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('email', 'jane.doe@example.com')
        ->set('phone', '07123456789')
        ->set('address', '1 Test Street')
        ->set('city', 'London')
        ->set('postcode', 'SW1A 1AA')
        ->call('savePersonalDetails')
        ->assertHasNoErrors()
        ->assertSet('step', 3)
        ->set('employmentHistories.0.company_name', 'Oakwood Primary')
        ->set('employmentHistories.0.job_title', 'Class Teacher')
        ->set('employmentHistories.0.worked_from', '2020-09-01')
        ->call('saveEmploymentHistory')
        ->assertSet('step', 4)
        ->set('skill_ids', [$childSkill->id])
        ->call('saveSkills')
        ->assertHasNoErrors()
        ->assertSet('step', 5)
        ->assertSee('Application Received');

    $candidate = EducationCandidate::where('email', 'jane.doe@example.com')->first();

    expect($candidate)->not->toBeNull();
    expect($candidate->company_id)->toBe($companyId);
    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');
    expect($candidate->phone)->toBe('07123456789');
    expect($candidate->address)->toBe('1 Test Street');
    expect($candidate->city)->toBe('London');
    expect($candidate->postcode)->toBe('SW1A 1AA');

    expect($candidate->employmentHistories()->count())->toBe(1);
    expect($candidate->employmentHistories()->first()->company_name)->toBe('Oakwood Primary');

    $skillIds = $candidate->skills()->pluck('candidate_skills.id')->all();
    expect($skillIds)->toContain($childSkill->id, $parentSkill->id);

    expect($candidate->statuses()->where('candidate_status_id', $onboarding->id)->exists())->toBeTrue();

    expect($candidate->activities()->where('note', 'like', 'Applied via the public link%')->exists())->toBeTrue();
    expect($vacancy->activities()->where('note', 'like', 'New applicant%')->exists())->toBeTrue();

    $application = $vacancy->applications()->first();
    expect($application)->not->toBeNull();
    expect($application->candidate_type)->toBe(EducationCandidate::class);
    expect($application->candidate_id)->toBe($candidate->id);
    expect($application->match_strength)->toBeNull();
    expect($candidate->vacancyApplications()->where('vacancy_id', $vacancy->id)->exists())->toBeTrue();
});

test('applying to a healthcare vacancy creates a healthcare candidate, proving the industry resolution is generic', function () {
    $vacancy = createVacancyFor('healthcare');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('first_name', 'Sam')
        ->set('last_name', 'Carter')
        ->set('email', 'sam.carter@example.com')
        ->call('savePersonalDetails')
        ->assertHasNoErrors()
        ->call('saveEmploymentHistory')
        ->set('skill_ids', [CandidateSkill::factory()->create([
            'company_id' => $vacancy->client->company_id,
            'industry_id' => $vacancy->client->industry_id,
        ])->id])
        ->call('saveSkills')
        ->assertHasNoErrors()
        ->assertSet('step', 5);

    expect(HealthcareCandidate::where('email', 'sam.carter@example.com')->exists())->toBeTrue();
    expect(EducationCandidate::where('email', 'sam.carter@example.com')->exists())->toBeFalse();
});

test('applying with an email that already belongs to a candidate updates that candidate instead of creating a duplicate', function () {
    $vacancy = createVacancyFor('education');
    $companyId = $vacancy->client->company_id;
    $industryId = $vacancy->client->industry_id;

    $existingStatus = CandidateStatus::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industryId,
        'name' => 'Available',
    ]);
    $existingSkill = CandidateSkill::factory()->create(['company_id' => $companyId, 'industry_id' => $industryId]);
    $newSkill = CandidateSkill::factory()->create(['company_id' => $companyId, 'industry_id' => $industryId]);

    $existingCandidate = EducationCandidate::factory()->create([
        'company_id' => $companyId,
        'first_name' => 'Old',
        'last_name' => 'Name',
        'email' => 'existing@example.com',
        'phone' => '00000000000',
    ]);
    $existingCandidate->skills()->attach($existingSkill->id);
    $existingCandidate->statuses()->create(['candidate_status_id' => $existingStatus->id]);

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('email', 'existing@example.com')
        ->set('phone', '07123456789')
        ->call('savePersonalDetails')
        ->assertHasNoErrors()
        ->assertSet('step', 3)
        ->call('saveEmploymentHistory')
        ->set('skill_ids', [$newSkill->id])
        ->call('saveSkills')
        ->assertHasNoErrors()
        ->assertSet('step', 5)
        ->assertSee('Welcome back');

    expect(EducationCandidate::where('email', 'existing@example.com')->count())->toBe(1);

    $existingCandidate->refresh();
    expect($existingCandidate->first_name)->toBe('Jane');
    expect($existingCandidate->last_name)->toBe('Doe');
    expect($existingCandidate->phone)->toBe('07123456789');

    // Previously recorded skills and status are kept, not wiped, by the update.
    $skillIds = $existingCandidate->skills()->pluck('candidate_skills.id')->all();
    expect($skillIds)->toContain($existingSkill->id, $newSkill->id);
    expect($existingCandidate->statuses()->where('candidate_status_id', $existingStatus->id)->exists())->toBeTrue();

    expect($vacancy->applications()->where('candidate_id', $existingCandidate->id)->exists())->toBeTrue();
    expect($vacancy->activities()->where('note', 'like', 'Returning applicant%')->exists())->toBeTrue();
});

test('applying twice to the same vacancy does not create a duplicate application row', function () {
    $vacancy = createVacancyFor('education');
    $skill = CandidateSkill::factory()->create([
        'company_id' => $vacancy->client->company_id,
        'industry_id' => $vacancy->client->industry_id,
    ]);

    $apply = function () use ($vacancy, $skill): void {
        Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
            ->call('skipCv')
            ->set('first_name', 'Jane')
            ->set('last_name', 'Doe')
            ->set('email', 'jane.doe@example.com')
            ->call('savePersonalDetails')
            ->call('saveEmploymentHistory')
            ->set('skill_ids', [$skill->id])
            ->call('saveSkills')
            ->assertHasNoErrors();
    };

    $apply();
    $apply();

    $candidate = EducationCandidate::where('email', 'jane.doe@example.com')->first();
    expect($vacancy->applications()->where('candidate_id', $candidate->id)->count())->toBe(1);
});

test('saveSkills requires at least one skill to be selected', function () {
    $vacancy = createVacancyFor('education');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('email', 'jane.doe@example.com')
        ->call('savePersonalDetails')
        ->call('saveEmploymentHistory')
        ->call('saveSkills')
        ->assertHasErrors(['skill_ids' => 'required']);

    expect(EducationCandidate::where('email', 'jane.doe@example.com')->exists())->toBeFalse();
});

test('selecting a cv automatically parses it and prefills personal details, address, and employment history', function () {
    CvParser::fake(fn () => [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'email' => 'jane.doe@example.com',
        'phone' => '07123456789',
        'address' => '1 Test Street',
        'city' => 'London',
        'county' => 'Greater London',
        'country' => 'United Kingdom',
        'postcode' => 'SW1A 1AA',
        'employmentHistory' => [
            [
                'companyName' => 'Oakwood Primary',
                'jobTitle' => 'Class Teacher',
                'workedFrom' => '2020-09-01',
                'workedTo' => null,
            ],
        ],
    ]);

    $vacancy = createVacancyFor('education');
    $file = UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf');

    // No explicit call to parseCv — selecting the file alone should trigger it.
    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->set('cv', $file)
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('first_name', 'Jane')
        ->assertSet('last_name', 'Doe')
        ->assertSet('email', 'jane.doe@example.com')
        ->assertSet('address', '1 Test Street')
        ->assertSet('city', 'London')
        ->assertSet('postcode', 'SW1A 1AA')
        ->assertSet('employmentHistories.0.company_name', 'Oakwood Primary');
});

test('the uploaded cv is attached to the candidate created at the end of the flow', function () {
    CvParser::fake(fn () => ['firstName' => 'Jane', 'lastName' => 'Doe']);

    $vacancy = createVacancyFor('education');
    $skill = CandidateSkill::factory()->create([
        'company_id' => $vacancy->client->company_id,
        'industry_id' => $vacancy->client->industry_id,
    ]);
    $file = UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->set('cv', $file)
        ->set('email', 'jane.doe@example.com')
        ->call('savePersonalDetails')
        ->assertHasNoErrors()
        ->call('saveEmploymentHistory')
        ->set('skill_ids', [$skill->id])
        ->call('saveSkills')
        ->assertHasNoErrors();

    $candidate = EducationCandidate::where('email', 'jane.doe@example.com')->first();
    $cvPath = $candidate->documents()->where('document_type', DocumentType::Cv)->value('path');

    expect($cvPath)->not->toBeNull();
    Storage::disk('local')->assertExists($cvPath);
});

test('generateUniqueSlug de-duplicates slugs for the same title', function () {
    $first = Vacancy::generateUniqueSlug('Year 3 Class Teacher');
    Vacancy::factory()->create(['slug' => $first]);

    $second = Vacancy::generateUniqueSlug('Year 3 Class Teacher');

    expect($first)->toBe('year-3-class-teacher');
    expect($second)->toBe('year-3-class-teacher-2');
    expect($second)->not->toBe($first);
});
