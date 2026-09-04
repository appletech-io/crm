<?php

use App\Enums\ReferenceStatus;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Models\Candidate;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Models\ReferenceForm;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    // GooglePlacesService caches autocomplete/place-details responses by
    // query/place ID — without this, a cached response from an earlier
    // test using the same fixture place ID (e.g. "place-1") would mask
    // this test's own Http::fake() response.
    Cache::flush();

    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'generic']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
});

test('this resource is not visible for the education or healthcare industries', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $educationIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $educationIndustry->id);

    expect(CandidateResource::canViewAny())->toBeFalse();
});

test('list page renders and shows candidates for the active industry only', function () {
    $ownCandidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $otherCandidate = Candidate::factory()->create();

    Livewire::test(ListCandidates::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$ownCandidate])
        ->assertCanNotSeeTableRecords([$otherCandidate]);
});

test('the list shows a candidate\'s average rating, and "Not yet rated" for one with none', function () {
    $rated = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'average_rating' => 4.5,
        'ratings_count' => 2,
    ]);
    $unrated = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListCandidates::class)
        ->assertCanSeeTableRecords([$rated, $unrated])
        ->assertSee('4.5 ★ (2)')
        ->assertSee('Not yet rated');
});

test('staff can create a candidate, which stamps company, industry, and consultant', function () {
    Livewire::test(CreateCandidate::class)
        ->fillForm([
            'first_name' => 'Robin',
            'last_name' => 'Shaw',
            'email' => 'robin.shaw@example.com',
            'job_title_id' => $this->jobTitle->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $candidate = Candidate::where('email', 'robin.shaw@example.com')->first();

    expect($candidate)->not->toBeNull()
        ->and($candidate->company_id)->toBe($this->company->id)
        ->and($candidate->industry_id)->toBe($this->industry->id)
        ->and($candidate->consultant_id)->toBe($this->user->id)
        ->and($candidate->job_title_id)->toBe($this->jobTitle->id);
});

test('staff can update a candidate\'s basic details', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'first_name' => 'Old',
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['first_name' => 'New'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->first_name)->toBe('New');
});

test('the list can be searched by phone number', function () {
    $match = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'phone' => '01234567890',
    ]);
    $other = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'phone' => '09999999999',
    ]);

    Livewire::test(ListCandidates::class)
        ->searchTable('01234567890')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

test('address defaults to search mode, and manual mode shows the plain fields with the reverse toggle label', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'address' => null,
        'postcode' => null,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormSet(['address_manual' => false])
        ->assertSee('Enter address manually')
        ->assertDontSee('Search address instead')
        ->fillForm(['address_manual' => true])
        ->assertSee('Search address instead')
        ->assertDontSee('Start typing an address or postcode');
});

test('address search returns suggestions from the google places autocomplete api', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [
                ['placePrediction' => ['placeId' => 'place-1', 'text' => ['text' => '10 Downing Street, London, UK']]],
            ],
        ], 200),
    ]);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'address' => null,
        'postcode' => null,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['address_search' => '10 Downing'])
        ->assertFormSet(['address_suggestions' => ['place-1' => '10 Downing Street, London, UK']]);
});

test('selecting an address suggestion populates the address fields from the google places details api', function () {
    Http::fake([
        'places.googleapis.com/v1/places/place-1*' => Http::response([
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

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'address' => null,
        'postcode' => null,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['address_suggestions' => ['place-1' => '10 Downing Street, London, UK']])
        ->fillForm(['address_place_id' => 'place-1'])
        ->assertFormSet([
            'address' => '10 Downing Street',
            'city' => 'London',
            'county' => 'Greater London',
            'country' => 'United Kingdom',
            'postcode' => 'SW1A 2AA',
        ]);
});

test('the edit page renders with all tabs, including widgets that need an existing record', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful();
});

test('the Personal Details tab shows a placeholder photo and "Not yet rated" when neither exist', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('No photo uploaded.')
        ->assertSee('Not yet rated');
});

test('the Personal Details tab shows the candidate\'s photo and average rating when both exist', function () {
    Storage::fake('local');
    Storage::disk('local')->put('candidate-documents/photo.jpg', 'fake image contents');

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'average_rating' => 4.5,
        'ratings_count' => 2,
    ]);
    $candidate->documents()->create([
        'document_type' => 'photo',
        'path' => 'candidate-documents/photo.jpg',
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('No photo uploaded.')
        ->assertSee('4.5 ★ (2 ratings)');
});

test('skills and pools can be attached via the Availability & Skills tab', function () {
    $skill = CandidateSkill::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $pool = CandidatePool::create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
        'name' => 'Shortlisted',
    ]);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'skills' => [$skill->id],
            'candidatePools' => [$pool->id],
            'notes' => 'Great communicator.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->skills->pluck('id')->all())->toBe([$skill->id])
        ->and($candidate->candidatePools->pluck('id')->all())->toBe([$pool->id])
        ->and($candidate->notes)->toBe('Great communicator.');
});

test('a pay rate can be added per job title via the Pay Rates tab', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'payRates' => [
                'item-1' => [
                    'job_title_id' => $this->jobTitle->id,
                    'day_rate' => '120.00',
                    'half_day_rate' => '60.00',
                    'hourly_rate' => '15.00',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $payRate = PayRate::where('model_id', $candidate->id)->where('model_type', Candidate::class)->first();

    expect($payRate)->not->toBeNull()
        ->and($payRate->job_title_id)->toBe($this->jobTitle->id)
        ->and($payRate->day_rate)->toEqual(120.0)
        ->and($payRate->half_day_rate)->toEqual(60.0)
        ->and($payRate->hourly_rate)->toEqual(15.0);
});

test('an employment history entry can be added via its tab', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'employmentHistories' => [
                'item-1' => [
                    'job_title' => 'Support Engineer',
                    'company_name' => 'Acme Ltd',
                    'worked_from' => '2020-01-01',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->employmentHistories()->count())->toBe(1)
        ->and($candidate->employmentHistories()->first()->job_title)->toBe('Support Engineer');
});

test('references can be viewed and saved via the repeater on the candidate edit form', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldExists('references')
        ->assertSuccessful();

    expect($candidate->references()->count())->toBe(1);
});

test('a gap/statement reference does not require a name when saving via the repeater', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $statementForm = ReferenceForm::factory()->statementOnly()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $reference = $candidate->references()->create([
        'reference_form_id' => $statementForm->id,
        'statement' => 'Travelling',
        'worked_from' => '2024-01-01',
        'worked_to' => '2024-06-01',
        'status' => 'confirmed',
    ])->fresh();

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set("data.references.record-{$reference->id}.statement", 'Travelling around Europe')
        ->call('save')
        ->assertHasNoFormErrors();

    $reference->refresh();
    expect($reference->statement)->toBe('Travelling around Europe');
    expect($reference->first_name)->toBeNull();
});

test('switching an existing candidate reference to gap/statement requires a statement instead of a name', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $statementForm = ReferenceForm::factory()->statementOnly()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ])->fresh();

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set("data.references.record-{$reference->id}.reference_form_id", $statementForm->id)
        ->call('save')
        ->assertHasFormErrors(["references.record-{$reference->id}.statement"]);
});

test('new candidate references default to pending status and can be moved through the workflow via the repeater', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ])->fresh();

    expect($reference->status)->toBe(ReferenceStatus::Pending);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set("data.references.record-{$reference->id}.status", 'confirmed')
        ->set("data.references.record-{$reference->id}.last_contacted", '2026-06-01')
        ->call('save')
        ->assertHasNoFormErrors();

    $reference->refresh();

    expect($reference->status)->toBe(ReferenceStatus::Confirmed)
        ->and($reference->last_contacted->toDateString())->toBe('2026-06-01');
});

test('the view reference response action is hidden until the reference has a token, then links to the reference form', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('View Reference Response');

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Contacted',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
        'status' => 'contacted',
        'token' => 'the-token',
        'expires_on' => now()->addDays(7),
    ]);

    $html = Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('View Reference Response')
        ->html();

    expect($html)->toContain(route('reference.form', ['token' => 'the-token']));
});

test('the resend reference action is hidden once submitted, and when the referee should not be contacted yet', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Never',
        'last_name' => 'Contacted',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
        'contact_now' => true,
        'email' => 'referee@example.com',
        'status' => 'pending',
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Send Reference Email');

    $submitted = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $submitted->references()->create([
        'type' => 'character',
        'first_name' => 'Already',
        'last_name' => 'Submitted',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
        'contact_now' => true,
        'email' => 'referee2@example.com',
        'status' => 'submitted',
        'token' => 'the-token',
        'expires_on' => now()->addDays(7),
    ]);

    Livewire::test(EditCandidate::class, ['record' => $submitted->getRouteKey()])
        ->assertDontSee('Send Reference Email')
        ->assertDontSee('Resend Reference Email');
});

test('the formatted CV content can be edited and saved from its tab', function () {
    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $candidate->formattedCv()->create(['content' => '<p>Original content</p>']);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'formattedCv' => ['content' => '<p>Edited content</p>'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->formattedCv()->first()->content)->toBe('<p>Edited content</p>');
});

test('saving the candidate form regenerates the formatted CV pdf from the saved content', function () {
    Storage::fake('local');

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    Storage::disk('local')->put('test-cvs/cv.pdf', 'fake pdf');
    $candidate->documents()->create(['document_type' => 'cv', 'path' => 'test-cvs/cv.pdf']);
    $candidate->formattedCv()->create(['content' => '<p>Some content</p>']);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'formattedCv' => ['content' => '<p>Some content</p>'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $pdfPath = $candidate->formattedCv()->first()->pdf_path;

    expect($pdfPath)->not->toBeNull();
    Storage::disk('local')->assertExists($pdfPath);
});

test('the Compliance tab renders one section per required item and saves field values, without touching real candidate columns', function () {
    $item = ComplianceItem::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id, 'name' => 'DBS']);
    $numberField = ComplianceItemField::factory()->create(['compliance_item_id' => $item->id, 'data_type' => 'text', 'name' => 'DBS Number']);
    $this->jobTitle->complianceItems()->attach($item->id, [
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $candidate = Candidate::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'job_title_id' => $this->jobTitle->id,
        'first_name' => 'Old',
    ]);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('DBS')
        ->assertSee('DBS Number')
        ->fillForm([
            'first_name' => 'New',
            "field_{$numberField->id}" => 'DBS-9999',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->first_name)->toBe('New')
        ->and($candidate->complianceValues()->where('compliance_item_field_id', $numberField->id)->first()->text_value)->toBe('DBS-9999');
});
