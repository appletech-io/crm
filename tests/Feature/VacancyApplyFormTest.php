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
use App\Services\Ai\CvParserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function makeFakeCvUpload(string $filename, string $contents = 'fake-cv-bytes'): TemporaryUploadedFile
{
    FileUploadConfiguration::storage()->put(FileUploadConfiguration::path($filename, false), $contents);

    return TemporaryUploadedFile::createFromLivewire($filename);
}

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
        ->assertSee('Upload your CV');
});

// This is a public, unauthenticated page — the client's identity is
// commercially sensitive and must never be exposed to a candidate (or a
// competitor) browsing a job ad.
test('the client name is never shown on the public apply page, at any step', function () {
    $vacancy = createVacancyFor('education');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->assertDontSee($vacancy->client->name)
        ->call('skipCv')
        ->assertDontSee($vacancy->client->name)
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('email', 'jane.doe@example.com')
        ->call('savePersonalDetails')
        ->assertDontSee($vacancy->client->name)
        ->call('saveEmploymentHistory')
        ->assertDontSee($vacancy->client->name);
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

test('applying end to end for an education vacancy creates a basic candidate with employment history and activity logs', function () {
    $vacancy = createVacancyFor('education');
    $companyId = $vacancy->client->company_id;
    $industryId = $vacancy->client->industry_id;

    $onboarding = CandidateStatus::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industryId,
        'name' => 'Onboarding',
    ]);

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
        ->assertHasNoErrors()
        ->assertSet('step', 4)
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

    expect($candidate->statuses()->where('candidate_status_id', $onboarding->id)->exists())->toBeTrue();

    expect($candidate->activities()->where('note', 'like', 'Applied via the public link%')->exists())->toBeTrue();
    expect($vacancy->activities()->where('note', 'like', 'New applicant%')->exists())->toBeTrue();

    $application = $vacancy->applications()->first();
    expect($application)->not->toBeNull();
    expect($application->candidate_type)->toBe(EducationCandidate::class);
    expect($application->candidate_id)->toBe($candidate->id);
    // No skills or location signal here, but the vacancy always has a job
    // title (a required field) and this candidate has one too ("Class
    // Teacher"), so JobTitleMatchScorer always has *some* signal — the
    // exact score depends on word overlap with the factory's random fake
    // job title, so only its shape is asserted, not an exact value.
    expect($application->match_strength)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
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
        ->assertHasNoErrors()
        ->assertSet('step', 4);

    expect(HealthcareCandidate::where('email', 'sam.carter@example.com')->exists())->toBeTrue();
    expect(EducationCandidate::where('email', 'sam.carter@example.com')->exists())->toBeFalse();
});

test('applying sets a match score on the resulting application when the candidate already has the vacancy\'s required skills', function () {
    $vacancy = createVacancyFor('education');
    $companyId = $vacancy->client->company_id;
    $industryId = $vacancy->client->industry_id;

    $skill = CandidateSkill::factory()->create(['company_id' => $companyId, 'industry_id' => $industryId]);
    $vacancy->skills()->attach($skill->id);

    // A returning candidate (matched by email) who already has the
    // required skill recorded from a previous application — the apply form
    // itself no longer collects skills, so this is the only realistic way
    // a candidate has any recorded today.
    $existingCandidate = EducationCandidate::factory()->create([
        'company_id' => $companyId,
        'email' => 'returning-with-skill@example.com',
    ]);
    $existingCandidate->skills()->attach($skill->id);

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('email', 'returning-with-skill@example.com')
        ->call('savePersonalDetails')
        ->call('saveEmploymentHistory')
        ->assertHasNoErrors();

    $application = $vacancy->applications()->where('candidate_id', $existingCandidate->id)->first();

    expect($application)->not->toBeNull();
    expect($application->match_strength)->toBe(100);
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

    $existingCandidate = EducationCandidate::factory()->create([
        'company_id' => $companyId,
        'first_name' => 'Old',
        'last_name' => 'Name',
        'email' => 'existing@example.com',
        'phone' => '00000000000',
    ]);
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
        ->assertHasNoErrors()
        ->assertSet('step', 4)
        ->assertSee('Welcome back');

    expect(EducationCandidate::where('email', 'existing@example.com')->count())->toBe(1);

    $existingCandidate->refresh();
    expect($existingCandidate->first_name)->toBe('Jane');
    expect($existingCandidate->last_name)->toBe('Doe');
    expect($existingCandidate->phone)->toBe('07123456789');

    // Previously recorded status is kept, not wiped, by the update — this
    // flow only ever touches the basic details it collects.
    expect($existingCandidate->statuses()->where('candidate_status_id', $existingStatus->id)->exists())->toBeTrue();

    expect($vacancy->applications()->where('candidate_id', $existingCandidate->id)->exists())->toBeTrue();
    expect($vacancy->activities()->where('note', 'like', 'Returning applicant%')->exists())->toBeTrue();
});

test('applying twice to the same vacancy does not create a duplicate application row', function () {
    $vacancy = createVacancyFor('education');

    $apply = function () use ($vacancy): void {
        Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
            ->call('skipCv')
            ->set('first_name', 'Jane')
            ->set('last_name', 'Doe')
            ->set('email', 'jane.doe@example.com')
            ->call('savePersonalDetails')
            ->call('saveEmploymentHistory')
            ->assertHasNoErrors();
    };

    $apply();
    $apply();

    $candidate = EducationCandidate::where('email', 'jane.doe@example.com')->first();
    expect($vacancy->applications()->where('candidate_id', $candidate->id)->count())->toBe(1);
});

test('applying a second time to the same vacancy shows already applied and logs no activity', function () {
    $vacancy = createVacancyFor('education');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('email', 'jane.doe@example.com')
        ->call('savePersonalDetails')
        ->call('saveEmploymentHistory')
        ->assertHasNoErrors();

    $candidate = EducationCandidate::where('email', 'jane.doe@example.com')->first();
    $candidateActivityCountAfterFirstApply = $candidate->activities()->count();
    $vacancyActivityCountAfterFirstApply = $vacancy->activities()->count();

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->set('first_name', 'Changed')
        ->set('last_name', 'Name')
        ->set('email', 'jane.doe@example.com')
        ->call('savePersonalDetails')
        ->call('saveEmploymentHistory')
        ->assertHasNoErrors()
        ->assertSet('alreadyApplied', true)
        ->assertSet('step', 4)
        ->assertSee('You have already applied');

    // No new activity logged for the repeat submission.
    expect($candidate->activities()->count())->toBe($candidateActivityCountAfterFirstApply);
    expect($vacancy->activities()->count())->toBe($vacancyActivityCountAfterFirstApply);

    // The candidate's details from the first application are untouched —
    // the repeat submission's changed name was never applied.
    $candidate->refresh();
    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');

    expect($vacancy->applications()->where('candidate_id', $candidate->id)->count())->toBe(1);
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
    $file = UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf');

    Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->set('cv', $file)
        ->set('email', 'jane.doe@example.com')
        ->call('savePersonalDetails')
        ->assertHasNoErrors()
        ->call('saveEmploymentHistory')
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

/**
 * Regression test: the original bug read the temp upload via
 * $file->getRealPath() + file_get_contents(). getRealPath() resolves
 * through the temp upload disk's path() method, which for a remote disk
 * (this app's default is S3) returns the raw storage key rather than a
 * real local filesystem path — file_get_contents() on that fails outright.
 * readStream() reads correctly regardless of which disk the temp file
 * actually lives on, so it's the only thing this asserts is called.
 */
test('cv parsing copies the temp upload via a stream rather than reading its real path', function () {
    config(['filesystems.default' => 's3']);
    CvParser::fake(fn () => ['firstName' => 'Jane', 'lastName' => 'Doe']);

    $vacancy = createVacancyFor('education');
    $file = makeFakeCvUpload('cv.pdf');

    Storage::shouldReceive('disk')->with('local')->andReturnSelf();
    Storage::shouldReceive('writeStream')
        ->once()
        ->withArgs(fn (string $path): bool => str_starts_with($path, 'vacancy-apply-cv-uploads/'))
        ->andReturnTrue();
    Storage::shouldReceive('path')->once()->andReturn('/tmp/fake-cv-for-test.pdf');
    Storage::shouldReceive('delete')->once()->andReturnTrue();
    Storage::shouldReceive('get')->never();
    Storage::shouldReceive('put')->never();

    // ->set('cv', $file) drives Livewire's own upload synthesizer, which
    // expects a file created via its normal upload flow — a
    // TemporaryUploadedFile built by hand for this test isn't one, so the
    // component's cv property is set directly instead, isolating the
    // assertion to parseCv()'s own file-handling rather than Livewire's
    // upload machinery (already covered by the CvParser::fake() tests above).
    $component = Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy]);
    $component->instance()->cv = $file;
    $component->instance()->parseCv(app(CvParserService::class));

    expect($component->instance()->parseError)->toBeNull();
});

/**
 * Regression test: the Title select used Flux's placeholder="" prop,
 * which renders a disabled, pre-selected blank <option>. Browsers refuse
 * to set a <select>'s value to a disabled option via JS, so as soon as
 * Livewire hydrates the page and tries to sync the select to the (null)
 * bound property, it silently snaps to the first real option ("Mr")
 * instead — and that gets submitted even though the candidate never
 * touched the field. The fix uses a real, non-disabled blank option.
 */
test('the title select renders a real, non-disabled blank option', function () {
    $vacancy = createVacancyFor('education');

    $html = Livewire::test('vacancy.apply-form', ['vacancy' => $vacancy])
        ->call('skipCv')
        ->html();

    expect($html)->not->toContain('disabled selected');
});
