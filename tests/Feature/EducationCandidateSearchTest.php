<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->industry = Industry::factory()->create(['slug' => 'education']);

    $this->consultant = User::factory()->create();
    $this->consultant->assignRole('consultant');
    $this->actingAs($this->consultant);

    Cache::put("user.{$this->consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->consultant->id}.active_industry_id", $this->industry->id);
});

function assignSearchCandidateStatus(EducationCandidate $candidate, string $statusName): void
{
    $status = CandidateStatus::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => test()->industry->id,
        'name' => $statusName,
    ]);

    CandidateCandidateStatus::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);
}

function makeSearchCandidate(array $attributes = []): EducationCandidate
{
    // array_key_exists, not ??, because a caller passing 'status' => null
    // (meaning "no status at all") must be distinguished from not passing
    // the key (meaning "default to Live") — ?? treats both the same way.
    $status = array_key_exists('status', $attributes) ? $attributes['status'] : 'Live';
    unset($attributes['status']);

    $candidate = EducationCandidate::factory()->create(array_merge([
        'company_id' => test()->consultant->company_id,
        'consultant_id' => test()->consultant->id,
    ], $attributes));

    if ($status !== null) {
        assignSearchCandidateStatus($candidate, $status);
    }

    return $candidate;
}

test('only candidates with a Live status are returned', function () {
    $liveCandidate = makeSearchCandidate(['status' => 'Live']);
    $onboardingCandidate = makeSearchCandidate(['status' => 'Onboarding']);
    $dnuCandidate = makeSearchCandidate(['status' => 'DNU']);
    $noStatusCandidate = makeSearchCandidate(['status' => null]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->assertCanSeeTableRecords([$liveCandidate])
        ->assertCanNotSeeTableRecords([$onboardingCandidate, $dnuCandidate, $noStatusCandidate]);
});

test('the search candidates section is collapsed by default', function () {
    $html = Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->html();

    expect($html)->toContain('search-candidates::data::section')
        ->toContain('isCollapsed:  true');
});

test('only the logged in consultants own candidates are returned, even for an admin', function () {
    $ownCandidate = makeSearchCandidate();

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);
    $otherConsultantCandidate = makeSearchCandidate(['consultant_id' => $otherConsultant->id]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->assertCanSeeTableRecords([$ownCandidate])
        ->assertCanNotSeeTableRecords([$otherConsultantCandidate]);

    // Now as an admin who owns no candidates of their own — should see none,
    // even though the general candidates list now shows the whole company.
    $admin = User::factory()->create(['company_id' => $this->consultant->company_id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$admin->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->assertCanNotSeeTableRecords([$ownCandidate, $otherConsultantCandidate]);
});

test('name filter narrows results', function () {
    $match = makeSearchCandidate(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $nonMatch = makeSearchCandidate(['first_name' => 'John', 'last_name' => 'Smith']);

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['name' => 'jane'])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$nonMatch]);
});

test('email filter narrows results', function () {
    $match = makeSearchCandidate(['email' => 'jane@example.com']);
    $nonMatch = makeSearchCandidate(['email' => 'john@other.com']);

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['email' => 'jane@'])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$nonMatch]);
});

test('skills filter narrows results', function () {
    $skill = CandidateSkill::factory()->create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $match = makeSearchCandidate();
    $match->skills()->attach($skill);

    $nonMatch = makeSearchCandidate();

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['skill_ids' => [$skill->id]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$nonMatch]);
});

test('client dropdown only offers clients belonging to the logged in consultant', function () {
    $ownClient = Client::factory()->create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'name' => 'My School',
    ]);

    Client::factory()->create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => null,
        'name' => 'Someone Elses School',
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->assertSee('My School')
        ->assertDontSee('Someone Elses School');
});

test('day filter excludes candidates booked on a selected day and respects cancellations', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $bookedCandidate = makeSearchCandidate();
    $booking = $bookedCandidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $cancelledBookingCandidate = makeSearchCandidate();
    $cancelledBooking = $cancelledBookingCandidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $cancelledBooking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
        'cancelled_at' => now(),
    ]);

    $freeCandidate = makeSearchCandidate();

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$cancelledBookingCandidate, $freeCandidate])
        ->assertCanNotSeeTableRecords([$bookedCandidate]);
});

test('selecting two days requires availability on both', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $tuesday = $monday->copy()->addDay();

    $bookedTuesday = makeSearchCandidate();
    $booking = $bookedTuesday->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => $tuesday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $tuesday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $freeBothDays = makeSearchCandidate();

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['days' => [1, 2]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$freeBothDays])
        ->assertCanNotSeeTableRecords([$bookedTuesday]);
});

test('location filter using a client keeps candidates inside the radius and excludes those outside it', function () {
    // Birmingham city centre. Postcode is explicitly null so the
    // ClientObserver doesn't dispatch a real geocode job (which runs
    // synchronously in tests) and overwrite these coordinates.
    $client = Client::factory()->create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'postcode' => null,
        'latitude' => 52.4862,
        'longitude' => -1.8904,
    ]);

    // ~2 miles away.
    $nearby = makeSearchCandidate(['latitude' => 52.4700, 'longitude' => -1.9000]);

    // ~200 miles away (London).
    $farAway = makeSearchCandidate(['latitude' => 51.5072, 'longitude' => -0.1276]);

    // No coordinates at all.
    $unlocated = makeSearchCandidate(['latitude' => null, 'longitude' => null]);

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['client_id' => $client->id, 'radius_miles' => 10])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$nearby])
        ->assertCanNotSeeTableRecords([$farAway, $unlocated]);
});

test('location filter using an address geocodes it and filters by radius', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'results' => [
                ['geometry' => ['location' => ['lat' => 52.4862, 'lng' => -1.8904]]],
            ],
            'status' => 'OK',
        ]),
    ]);

    $nearby = makeSearchCandidate(['latitude' => 52.4700, 'longitude' => -1.9000]);
    $farAway = makeSearchCandidate(['latitude' => 51.5072, 'longitude' => -0.1276]);

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['address' => 'Birmingham City Centre', 'radius_miles' => 10])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$nearby])
        ->assertCanNotSeeTableRecords([$farAway]);
});

test('day columns have a non-blank state so the icon actually renders rather than a blank placeholder cell', function () {
    $candidate = makeSearchCandidate();

    $test = Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search');

    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getState())->not->toBeNull()
        ->and($column->getIcon($column->getState()))->not->toBeNull();
});

test('clicking an available day column selects it and shows the book action with that date', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeSearchCandidate();

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->assertTableActionHidden('book', record: $candidate)
        ->callTableColumnAction('day_1', $candidate)
        ->assertTableActionVisible('book', record: $candidate)
        ->assertTableActionHasUrl('book', BookingResource::getUrl('create', [
            'candidate_id' => $candidate->id,
            'client_id' => null,
            'dates' => [$monday->toDateString()],
        ]), record: $candidate);
});

test('clicking an already-booked day does nothing', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeSearchCandidate();

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->callTableColumnAction('day_1', $candidate)
        ->assertTableActionHidden('book', record: $candidate);
});

test('selecting non-contiguous days for the book action includes only those dates, and a selected client is included', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $wednesday = $monday->copy()->addDays(2);
    $candidate = makeSearchCandidate();
    $client = Client::factory()->create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'postcode' => null,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['client_id' => $client->id])
        ->set('activeSection', 'search')
        ->callTableColumnAction('day_1', $candidate)
        ->callTableColumnAction('day_3', $candidate)
        ->assertTableActionHasUrl('book', BookingResource::getUrl('create', [
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'dates' => [$monday->toDateString(), $wednesday->toDateString()],
        ]), record: $candidate);
});

test('day selections for one candidate do not affect another', function () {
    $candidateA = makeSearchCandidate();
    $candidateB = makeSearchCandidate();

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->callTableColumnAction('day_1', $candidateA)
        ->assertTableActionVisible('book', record: $candidateA)
        ->assertTableActionHidden('book', record: $candidateB);
});

test('clicking a selected day again deselects it and hides the book action once no days remain selected', function () {
    $candidate = makeSearchCandidate();

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->callTableColumnAction('day_1', $candidate)
        ->assertTableActionVisible('book', record: $candidate)
        ->callTableColumnAction('day_1', $candidate)
        ->assertTableActionHidden('book', record: $candidate);
});

test('the tab bar renders on both the search and all candidates pages with the correct tab active', function () {
    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->assertSeeHtml('fi-active')
        ->assertSee('Search')
        ->assertSee('All Candidates');

    Livewire::test(ListEducationCandidates::class)
        ->assertSeeHtml('fi-active')
        ->assertSee('Search')
        ->assertSee('All Candidates');
});
