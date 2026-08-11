<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\CandidateAvailabilityStatus;
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
use Illuminate\Contracts\Support\Htmlable;
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

test('the day filter excludes a candidate explicitly marked Not Available that day', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $notAvailableCandidate = makeSearchCandidate();
    $notAvailableCandidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    $freeCandidate = makeSearchCandidate();

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$freeCandidate])
        ->assertCanNotSeeTableRecords([$notAvailableCandidate]);
});

test('the day filter includes a candidate explicitly marked Available, Available AM, or Available PM that day', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $available = makeSearchCandidate();
    $available->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    $availableAm = makeSearchCandidate();
    $availableAm->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailableAm->value,
    ]);

    $availablePm = makeSearchCandidate();
    $availablePm->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailablePm->value,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$available, $availableAm, $availablePm]);
});

test('the day filter still includes a candidate with no availability recorded for that day at all', function () {
    $noDataCandidate = makeSearchCandidate();

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$noDataCandidate]);
});

test('a Not Available status on a different day does not exclude the candidate from the searched day', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $tuesday = $monday->copy()->addDay();

    $candidate = makeSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $tuesday->toDateString(),
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$candidate]);
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

test('the rating column on the search page is sortable', function () {
    $lowRated = makeSearchCandidate();
    $lowRated->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => now()->toDateString(),
        'status' => BookingStatus::Completed,
        'candidate_rating' => 2,
        'candidate_rated_at' => now(),
    ]);

    $highRated = makeSearchCandidate();
    $highRated->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => now()->toDateString(),
        'status' => BookingStatus::Completed,
        'candidate_rating' => 5,
        'candidate_rated_at' => now(),
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->sortTable('average_rating')
        ->assertCanSeeTableRecords([$lowRated, $highRated], inOrder: true)
        ->sortTable('average_rating', 'desc')
        ->assertCanSeeTableRecords([$highRated, $lowRated], inOrder: true);
});

test('the availability column on the search page shows how many of the 5 days are free', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $fullyFree = makeSearchCandidate();

    $partiallyBooked = makeSearchCandidate();
    $booking = $partiallyBooked->bookings()->create([
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
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->copy()->addDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->assertTableColumnStateSet('availability_score', '5/5 available', record: $fullyFree)
        ->assertTableColumnStateSet('availability_score', '3/5 available', record: $partiallyBooked);
});

test('the availability column on the search page is sortable, most available first', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $fullyFree = makeSearchCandidate();

    $mostlyBooked = makeSearchCandidate();
    foreach ([0, 1, 2] as $dayOffset) {
        $booking = $mostlyBooked->bookings()->create([
            'company_id' => $this->consultant->company_id,
            'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
            'candidate_type' => EducationCandidate::class,
            'start_date' => $monday->copy()->addDays($dayOffset)->toDateString(),
            'status' => BookingStatus::Upcoming,
        ]);
        $booking->dayPeriods()->create([
            'company_id' => $this->consultant->company_id,
            'date' => $monday->copy()->addDays($dayOffset)->toDateString(),
            'period' => BookingDayPeriod::FullDay,
        ]);
    }

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->sortTable('availability_score', 'desc')
        ->assertCanSeeTableRecords([$fullyFree, $mostlyBooked], inOrder: true)
        ->sortTable('availability_score', 'asc')
        ->assertCanSeeTableRecords([$mostlyBooked, $fullyFree], inOrder: true);
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
            'periods' => [$monday->toDateString() => 'full_day'],
        ]), record: $candidate);
});

test('selecting a day marked Available AM carries the am period through to the book action url', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailableAm->value,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->callTableColumnAction('day_1', $candidate)
        ->assertTableActionHasUrl('book', BookingResource::getUrl('create', [
            'candidate_id' => $candidate->id,
            'client_id' => null,
            'dates' => [$monday->toDateString()],
            'periods' => [$monday->toDateString() => BookingDayPeriod::Am->value],
        ]), record: $candidate);
});

test('selecting a day marked Available PM carries the pm period through to the book action url', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailablePm->value,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'search')
        ->callTableColumnAction('day_1', $candidate)
        ->assertTableActionHasUrl('book', BookingResource::getUrl('create', [
            'candidate_id' => $candidate->id,
            'client_id' => null,
            'dates' => [$monday->toDateString()],
            'periods' => [$monday->toDateString() => BookingDayPeriod::Pm->value],
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
            'periods' => [$monday->toDateString() => 'full_day', $wednesday->toDateString() => 'full_day'],
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

test('a day with no availability set shows the unsure icon and is still selectable', function () {
    $candidate = makeSearchCandidate();

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getIcon($column->getState()))->toBe('heroicon-o-question-mark-circle');
    expect($column->getColor($column->getState()))->toBe('warning');
});

test('a day marked Available shows a green tick and is selectable', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getIcon($column->getState()))->toBe('heroicon-o-check-circle');
    expect($column->getColor($column->getState()))->toBe('success');

    $test->callTableColumnAction('day_1', $candidate)
        ->assertTableActionVisible('book', record: $candidate);
});

test('a day marked Not Available shows a red cross and cannot be selected', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getIcon($column->getState()))->toBe('heroicon-o-x-circle');
    expect($column->getColor($column->getState()))->toBe('danger');

    $test->callTableColumnAction('day_1', $candidate)
        ->assertTableActionHidden('book', record: $candidate);
});

test('an already-booked day shows a blue tick', function () {
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

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getIcon($column->getState()))->toBe('heroicon-o-check-circle');
    expect($column->getColor($column->getState()))->toBe('info');
});

test('a morning-only booking shows a blue half-circle, top filled', function () {
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
        'period' => BookingDayPeriod::Am,
    ]);

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getColor($column->getState()))->toBe('info');

    $icon = $column->getIcon($column->getState());
    expect($icon)->toBeInstanceOf(Htmlable::class);
    expect($icon->toHtml())->toContain('0 0 1');
});

test('an afternoon-only booking shows a blue half-circle, bottom filled', function () {
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
        'period' => BookingDayPeriod::Pm,
    ]);

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getColor($column->getState()))->toBe('info');

    $icon = $column->getIcon($column->getState());
    expect($icon)->toBeInstanceOf(Htmlable::class);
    expect($icon->toHtml())->toContain('0 0 0');
});

test('separate morning and afternoon bookings on the same day together show a solid blue tick', function () {
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
        'period' => BookingDayPeriod::Am,
    ]);

    $otherBooking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => EducationCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $otherBooking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::Pm,
    ]);

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getIcon($column->getState()))->toBe('heroicon-o-check-circle');
    expect($column->getColor($column->getState()))->toBe('info');
});

test('an "hours" period booking shows a solid blue tick, not a half-circle', function () {
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
        'period' => BookingDayPeriod::Hours,
        'time_from' => '09:00',
        'time_to' => '11:00',
    ]);

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getIcon($column->getState()))->toBe('heroicon-o-check-circle');
    expect($column->getColor($column->getState()))->toBe('info');
});

test('a booking always takes precedence over a stored Available status for the same day', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

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

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getColor($column->getState()))->toBe('info');
});

test('AM and PM availability render as distinct green half-circle icons', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $amCandidate = makeSearchCandidate();
    $amCandidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailableAm->value,
    ]);

    $pmCandidate = makeSearchCandidate();
    $pmCandidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailablePm->value,
    ]);

    $test = Livewire::test(ListEducationCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');

    $column->record($amCandidate);
    expect($column->getColor($column->getState()))->toBe('success');
    $amIcon = $column->getIcon($column->getState());
    expect($amIcon)->toBeInstanceOf(Htmlable::class);

    $column->record($pmCandidate);
    $pmIcon = $column->getIcon($column->getState());
    expect($pmIcon)->toBeInstanceOf(Htmlable::class);

    expect($amIcon->toHtml())->not->toBe($pmIcon->toHtml());
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
