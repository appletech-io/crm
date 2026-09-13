<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\CandidateAvailabilityStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Client;
use App\Models\HealthcareCandidate;
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

    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);

    $this->consultant = User::factory()->create();
    $this->consultant->assignRole('consultant');
    $this->actingAs($this->consultant);

    Cache::put("user.{$this->consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->consultant->id}.active_industry_id", $this->industry->id);
});

function assignHealthcareSearchCandidateStatus(HealthcareCandidate $candidate, string $statusName): void
{
    $status = CandidateStatus::factory()->create([
        'company_id' => $candidate->company_id,
        'industry_id' => test()->industry->id,
        'name' => $statusName,
    ]);

    CandidateCandidateStatus::create([
        'model_type' => HealthcareCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);
}

function makeHealthcareSearchCandidate(array $attributes = []): HealthcareCandidate
{
    // array_key_exists, not ??, because a caller passing 'status' => null
    // (meaning "no status at all") must be distinguished from not passing
    // the key (meaning "default to Live") — ?? treats both the same way.
    $status = array_key_exists('status', $attributes) ? $attributes['status'] : 'Live';
    unset($attributes['status']);

    $candidate = HealthcareCandidate::factory()->create(array_merge([
        'company_id' => test()->consultant->company_id,
        'consultant_id' => test()->consultant->id,
    ], $attributes));

    if ($status !== null) {
        assignHealthcareSearchCandidateStatus($candidate, $status);
    }

    return $candidate;
}

test('only candidates with a Live status are returned', function () {
    $liveCandidate = makeHealthcareSearchCandidate(['status' => 'Live']);
    $onboardingCandidate = makeHealthcareSearchCandidate(['status' => 'Onboarding']);
    $noStatusCandidate = makeHealthcareSearchCandidate(['status' => null]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->assertCanSeeTableRecords([$liveCandidate])
        ->assertCanNotSeeTableRecords([$onboardingCandidate, $noStatusCandidate]);
});

test('the search candidates section is collapsed by default', function () {
    $html = Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->html();

    expect($html)->toContain('search-candidates::data::section')
        ->toContain('isCollapsed:  true');
});

test('only the logged in consultants own candidates are returned, even for an admin', function () {
    $ownCandidate = makeHealthcareSearchCandidate();

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);
    $otherConsultantCandidate = makeHealthcareSearchCandidate(['consultant_id' => $otherConsultant->id]);

    Livewire::test(ListHealthcareCandidates::class)
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

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->assertCanNotSeeTableRecords([$ownCandidate, $otherConsultantCandidate]);
});

test('name filter narrows results', function () {
    $match = makeHealthcareSearchCandidate(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $nonMatch = makeHealthcareSearchCandidate(['first_name' => 'John', 'last_name' => 'Smith']);

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['name' => 'jane'])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$nonMatch]);
});

test('email filter narrows results', function () {
    $match = makeHealthcareSearchCandidate(['email' => 'jane@example.com']);
    $nonMatch = makeHealthcareSearchCandidate(['email' => 'john@other.com']);

    Livewire::test(ListHealthcareCandidates::class)
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

    $match = makeHealthcareSearchCandidate();
    $match->skills()->attach($skill);

    $nonMatch = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['skill_ids' => [$skill->id]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$nonMatch]);
});

test('pools filter narrows results', function () {
    $pool = CandidatePool::create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->consultant->id,
        'name' => 'Shortlist',
    ]);

    $match = makeHealthcareSearchCandidate();
    $pool->candidatesOfType(HealthcareCandidate::class)->attach($match->id);

    $nonMatch = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['pool_ids' => [$pool->id]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$nonMatch]);
});

test('day filter excludes candidates booked on a selected day and respects cancellations', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $bookedCandidate = makeHealthcareSearchCandidate();
    $booking = $bookedCandidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $cancelledBookingCandidate = makeHealthcareSearchCandidate();
    $cancelledBooking = $cancelledBookingCandidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $cancelledBooking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
        'cancelled_at' => now(),
    ]);

    $freeCandidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$cancelledBookingCandidate, $freeCandidate])
        ->assertCanNotSeeTableRecords([$bookedCandidate]);
});

test('the day filter excludes a candidate explicitly marked Not Available that day', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $notAvailableCandidate = makeHealthcareSearchCandidate();
    $notAvailableCandidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    $freeCandidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$freeCandidate])
        ->assertCanNotSeeTableRecords([$notAvailableCandidate]);
});

test('the day filter still includes a candidate with no availability recorded for that day at all', function () {
    $noDataCandidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$noDataCandidate]);
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
    $nearby = makeHealthcareSearchCandidate(['latitude' => 52.4700, 'longitude' => -1.9000]);

    // ~200 miles away (London).
    $farAway = makeHealthcareSearchCandidate(['latitude' => 51.5072, 'longitude' => -0.1276]);

    // No coordinates at all.
    $unlocated = makeHealthcareSearchCandidate(['latitude' => null, 'longitude' => null]);

    Livewire::test(ListHealthcareCandidates::class)
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

    $nearby = makeHealthcareSearchCandidate(['latitude' => 52.4700, 'longitude' => -1.9000]);
    $farAway = makeHealthcareSearchCandidate(['latitude' => 51.5072, 'longitude' => -0.1276]);

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['address' => 'Birmingham City Centre', 'radius_miles' => 10])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$nearby])
        ->assertCanNotSeeTableRecords([$farAway]);
});

test('the availability column on the search page shows how many of the 5 days are free', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $fullyFree = makeHealthcareSearchCandidate();

    $partiallyBooked = makeHealthcareSearchCandidate();
    $booking = $partiallyBooked->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
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

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->assertTableColumnStateSet('availability_score', '5/5 available', record: $fullyFree)
        ->assertTableColumnStateSet('availability_score', '3/5 available', record: $partiallyBooked);
});

test('day columns have a non-blank state so the icon actually renders rather than a blank placeholder cell', function () {
    $candidate = makeHealthcareSearchCandidate();

    $test = Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search');

    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    $state = $column->getState();

    expect($state)->not->toBeNull()
        ->and($state['icon'])->not->toBeNull();
});

test('clicking an available day column selects it and shows the book action with that date', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->assertTableActionHidden('book', record: $candidate)
        ->call('handleDayClick', $candidate->id, 1)
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
    $candidate = makeHealthcareSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailableAm->value,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('handleDayClick', $candidate->id, 1)
        ->assertTableActionHasUrl('book', BookingResource::getUrl('create', [
            'candidate_id' => $candidate->id,
            'client_id' => null,
            'dates' => [$monday->toDateString()],
            'periods' => [$monday->toDateString() => BookingDayPeriod::Am->value],
        ]), record: $candidate);
});

test('selecting a day marked Available PM carries the pm period through to the book action url', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailablePm->value,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('handleDayClick', $candidate->id, 1)
        ->assertTableActionHasUrl('book', BookingResource::getUrl('create', [
            'candidate_id' => $candidate->id,
            'client_id' => null,
            'dates' => [$monday->toDateString()],
            'periods' => [$monday->toDateString() => BookingDayPeriod::Pm->value],
        ]), record: $candidate);
});

test('clicking an already-booked day does nothing', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('handleDayClick', $candidate->id, 1)
        ->assertTableActionHidden('book', record: $candidate);
});

test('selecting non-contiguous days for the book action includes only those dates, and a selected client is included', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $wednesday = $monday->copy()->addDays(2);
    $candidate = makeHealthcareSearchCandidate();
    $client = Client::factory()->create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'postcode' => null,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['client_id' => $client->id])
        ->set('activeSection', 'search')
        ->call('handleDayClick', $candidate->id, 1)
        ->call('handleDayClick', $candidate->id, 3)
        ->assertTableActionHasUrl('book', BookingResource::getUrl('create', [
            'candidate_id' => $candidate->id,
            'client_id' => $client->id,
            'dates' => [$monday->toDateString(), $wednesday->toDateString()],
            'periods' => [$monday->toDateString() => 'full_day', $wednesday->toDateString() => 'full_day'],
        ]), record: $candidate);
});

test('day selections for one candidate do not affect another', function () {
    $candidateA = makeHealthcareSearchCandidate();
    $candidateB = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('handleDayClick', $candidateA->id, 1)
        ->assertTableActionVisible('book', record: $candidateA)
        ->assertTableActionHidden('book', record: $candidateB);
});

test('clicking a selected day again deselects it and hides the book action once no days remain selected', function () {
    $candidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('handleDayClick', $candidate->id, 1)
        ->assertTableActionVisible('book', record: $candidate)
        ->call('handleDayClick', $candidate->id, 1)
        ->assertTableActionHidden('book', record: $candidate);
});

test('a day with no availability set shows the unsure icon and is still selectable', function () {
    $candidate = makeHealthcareSearchCandidate();

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    $state = $column->getState();

    expect($state['icon'])->toBe('heroicon-o-question-mark-circle');
    expect($state['colorClasses'])->toContain('amber');
});

test('a day marked Available shows a green tick and is selectable', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    $state = $column->getState();

    expect($state['icon'])->toBe('heroicon-o-check-circle');
    expect($state['colorClasses'])->toContain('green');

    $test->call('handleDayClick', $candidate->id, 1)
        ->assertTableActionVisible('book', record: $candidate);
});

test('a day marked Not Available shows a red cross and cannot be selected', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    $state = $column->getState();

    expect($state['icon'])->toBe('heroicon-o-x-circle');
    expect($state['colorClasses'])->toContain('red');

    $test->call('handleDayClick', $candidate->id, 1)
        ->assertTableActionHidden('book', record: $candidate);
});

test('an already-booked day shows a blue tick', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    $state = $column->getState();

    expect($state['icon'])->toBe('heroicon-o-check-circle');
    expect($state['colorClasses'])->toContain('blue');
});

test('the tooltip on an already-booked day shows the client, pay rate, and charge rate', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();
    $client = Client::factory()->create(['company_id' => $this->consultant->company_id, 'name' => 'Riverside Hospital']);

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => $client->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
        'day_rate' => 120,
        'day_charge_rate' => 150,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getState()['tooltip'])
        ->toBe('Riverside Hospital — Pay £120.00 / Charge £150.00');
});

test('the tooltip labels each period separately when morning and afternoon are different bookings', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();
    $morningClient = Client::factory()->create(['company_id' => $this->consultant->company_id, 'name' => 'Riverside Hospital']);
    $afternoonClient = Client::factory()->create(['company_id' => $this->consultant->company_id, 'name' => 'Oakwood Clinic']);

    $morningBooking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => $morningClient->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
        'half_day_rate' => 60,
        'half_day_charge_rate' => 75,
    ]);
    $morningBooking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::Am,
    ]);

    $afternoonBooking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => $afternoonClient->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
        'half_day_rate' => 55,
        'half_day_charge_rate' => 70,
    ]);
    $afternoonBooking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::Pm,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getState()['tooltip'])
        ->toBe('AM: Riverside Hospital — Pay £60.00 / Charge £75.00 | PM: Oakwood Clinic — Pay £55.00 / Charge £70.00');
});

test('a morning-only booking shows a blue half-circle, top filled', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::Am,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    $state = $column->getState();

    expect($state['colorClasses'])->toContain('blue');

    $icon = $state['icon'];
    expect($icon)->toBeInstanceOf(Htmlable::class);
    expect($icon->toHtml())->toContain('0 0 1');
});

test('an afternoon-only booking shows a blue half-circle, bottom filled', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::Pm,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    $state = $column->getState();

    expect($state['colorClasses'])->toContain('blue');

    $icon = $state['icon'];
    expect($icon)->toBeInstanceOf(Htmlable::class);
    expect($icon->toHtml())->toContain('0 0 0');
});

test('separate morning and afternoon bookings on the same day together show a solid blue tick', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
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
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $otherBooking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::Pm,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    $state = $column->getState();

    expect($state['icon'])->toBe('heroicon-o-check-circle');
    expect($state['colorClasses'])->toContain('blue');
});

test('a booking always takes precedence over a stored Available status for the same day', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getState()['colorClasses'])->toContain('blue');
});

test('AM and PM availability render as distinct green half-circle icons', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $amCandidate = makeHealthcareSearchCandidate();
    $amCandidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailableAm->value,
    ]);

    $pmCandidate = makeHealthcareSearchCandidate();
    $pmCandidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::AvailablePm->value,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');
    $column = $test->instance()->getTable()->getColumn('day_1');

    $column->record($amCandidate);
    $amState = $column->getState();
    expect($amState['colorClasses'])->toContain('green');
    $amIcon = $amState['icon'];
    expect($amIcon)->toBeInstanceOf(Htmlable::class);

    $column->record($pmCandidate);
    $pmIcon = $column->getState()['icon'];
    expect($pmIcon)->toBeInstanceOf(Htmlable::class);

    expect($amIcon->toHtml())->not->toBe($pmIcon->toHtml());
});

test('the tab bar renders on both the search and all candidates pages with the correct tab active', function () {
    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->assertSeeHtml('fi-active')
        ->assertSee('Search')
        ->assertSee('All Candidates');

    Livewire::test(ListHealthcareCandidates::class)
        ->assertSeeHtml('fi-active')
        ->assertSee('Search')
        ->assertSee('All Candidates');
});

test('the availability grid defaults to the current week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    expect($test->get('weekStart'))->toBe($monday->toDateString());
});

test('next/previous/current week navigation moves the grid a week at a time', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    $test->call('goToNextWeek');
    expect($test->get('weekStart'))->toBe($monday->copy()->addWeek()->toDateString());

    $test->call('goToNextWeek');
    expect($test->get('weekStart'))->toBe($monday->copy()->addWeeks(2)->toDateString());

    $test->call('goToPreviousWeek')->call('goToPreviousWeek')->call('goToPreviousWeek');
    expect($test->get('weekStart'))->toBe($monday->copy()->subWeek()->toDateString());

    $test->call('goToCurrentWeek');
    expect($test->get('weekStart'))->toBe($monday->toDateString());
});

test('navigating to next week shows availability recorded for that week instead of the current one', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $nextMonday = $monday->copy()->addWeek();

    $candidate = makeHealthcareSearchCandidate();
    $candidate->availabilities()->create([
        'date' => $nextMonday->toDateString(),
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    expect($column->getState()['status'])->toBeNull();

    $test->call('goToNextWeek');

    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    expect($column->getState()['status'])->toBe(CandidateAvailabilityStatus::Available->value);
});

test('clicking a day column after navigating weeks selects a date in the navigated week, not the current one', function () {
    $nextMonday = now()->startOfWeek(Carbon::MONDAY)->addWeek();
    $candidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('goToNextWeek')
        ->call('handleDayClick', $candidate->id, 1)
        ->assertTableActionHasUrl('book', BookingResource::getUrl('create', [
            'candidate_id' => $candidate->id,
            'client_id' => null,
            'dates' => [$nextMonday->toDateString()],
            'periods' => [$nextMonday->toDateString() => 'full_day'],
        ]), record: $candidate);
});

test('the days filter follows the navigated week, not always the real current week', function () {
    $nextMonday = now()->startOfWeek(Carbon::MONDAY)->addWeek();

    // Booked next Monday — should be excluded from a Monday search only
    // once the grid has actually navigated to next week.
    $bookedNextMonday = makeHealthcareSearchCandidate();
    $booking = $bookedNextMonday->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $nextMonday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $nextMonday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $freeCandidate = makeHealthcareSearchCandidate();

    // Still on the current week — the booking is next week, so both
    // candidates look free for "Monday".
    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$bookedNextMonday, $freeCandidate]);

    // After navigating to next week, the same Monday filter now means next
    // Monday, so the booked candidate is correctly excluded.
    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('goToNextWeek')
        ->call('search')
        ->assertCanSeeTableRecords([$freeCandidate])
        ->assertCanNotSeeTableRecords([$bookedNextMonday]);
});

test('the quick-set action saves a new availability status for an unknown day', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('setQuickAvailability', $candidate->id, $monday->toDateString(), CandidateAvailabilityStatus::AvailableAm->value);

    expect($candidate->availabilities()->whereDate('date', $monday->toDateString())->first()->status)
        ->toBe(CandidateAvailabilityStatus::AvailableAm);
});

test('the quick-set action updates an existing availability record rather than duplicating it', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();
    $existing = $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('setQuickAvailability', $candidate->id, $monday->toDateString(), CandidateAvailabilityStatus::Available->value);

    expect($candidate->availabilities()->count())->toBe(1);
    expect($existing->fresh()->status)->toBe(CandidateAvailabilityStatus::Available);
});

test('the quick-set action rejects a status outside Full/AM/PM', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('setQuickAvailability', $candidate->id, $monday->toDateString(), CandidateAvailabilityStatus::NotAvailable->value);

    expect($candidate->availabilities()->count())->toBe(0);
});

test('the quick-set action does nothing for a day that is actually booked', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $monday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('setQuickAvailability', $candidate->id, $monday->toDateString(), CandidateAvailabilityStatus::Available->value);

    expect($candidate->availabilities()->count())->toBe(0);
});

test('staging a status for a day does not save it until the row Save action runs', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    $test->call('stageAvailability', $candidate->id, 1, CandidateAvailabilityStatus::AvailableAm->value);

    expect($candidate->availabilities()->count())->toBe(0);

    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    expect($column->getState()['pendingStatus'])->toBe(CandidateAvailabilityStatus::AvailableAm->value);

    $test->call('saveStagedAvailability', $candidate->id);

    expect($candidate->availabilities()->whereDate('date', $monday->toDateString())->first()->status)
        ->toBe(CandidateAvailabilityStatus::AvailableAm);
});

test('staging the same status twice for a day un-stages it', function () {
    $candidate = makeHealthcareSearchCandidate();

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    $test->call('stageAvailability', $candidate->id, 1, CandidateAvailabilityStatus::Available->value);

    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    expect($column->getState()['pendingStatus'])->toBe(CandidateAvailabilityStatus::Available->value);

    $test->call('stageAvailability', $candidate->id, 1, CandidateAvailabilityStatus::Available->value);

    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);
    expect($column->getState()['pendingStatus'])->toBeNull();
});

test('the row Save action saves each staged day with its own chosen status, and only those days', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $tuesday = $monday->copy()->addDay();
    $candidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('stageAvailability', $candidate->id, 1, CandidateAvailabilityStatus::Available->value)
        ->call('stageAvailability', $candidate->id, 2, CandidateAvailabilityStatus::AvailableAm->value)
        ->call('saveStagedAvailability', $candidate->id);

    expect($candidate->availabilities()->whereDate('date', $monday->toDateString())->first()->status)
        ->toBe(CandidateAvailabilityStatus::Available)
        ->and($candidate->availabilities()->whereDate('date', $tuesday->toDateString())->first()->status)
        ->toBe(CandidateAvailabilityStatus::AvailableAm)
        ->and($candidate->availabilities()->count())->toBe(2);
});

test('the row Save action leaves already-set and booked days untouched and skips a staged but now-booked day', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $tuesday = $monday->copy()->addDay();
    $candidate = makeHealthcareSearchCandidate();

    $notAvailable = $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $tuesday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $tuesday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        // Staged before the booking existed — still guarded at save time.
        ->call('stageAvailability', $candidate->id, 2, CandidateAvailabilityStatus::Available->value)
        ->call('saveStagedAvailability', $candidate->id);

    expect($notAvailable->fresh()->status)->toBe(CandidateAvailabilityStatus::NotAvailable)
        ->and($candidate->availabilities()->whereDate('date', $tuesday->toDateString())->exists())->toBeFalse();
});

test('the Save action clears the staged selection for that row after saving', function () {
    $candidate = makeHealthcareSearchCandidate();

    $test = Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->call('stageAvailability', $candidate->id, 1, CandidateAvailabilityStatus::Available->value)
        ->call('saveStagedAvailability', $candidate->id);

    $column = $test->instance()->getTable()->getColumn('day_1');
    $column->record($candidate);

    expect($column->getState()['pendingStatus'])->toBeNull();
});

test('the row no longer links through to the candidate edit page', function () {
    $candidate = makeHealthcareSearchCandidate();

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    // ListRecords always installs a fallback recordUrl closure that looks
    // for a registered 'view'/'edit'-named row action (see Filament's
    // ListRecords::table()) — configureSearchTable() no longer explicitly
    // sets ->recordUrl() itself, and registers no action named that, so the
    // fallback resolves to null: the row genuinely isn't a link anymore.
    expect($test->instance()->getTable()->getRecordUrl($candidate))->toBeNull();
});

test('the name column shows first and last name together and links to quick view instead of editing', function () {
    $candidate = makeHealthcareSearchCandidate(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    $test->assertSee('Jane Doe');

    $column = $test->instance()->getTable()->getColumn('name');
    $column->record($candidate);

    expect($column->getState())->toBe('Jane Doe')
        ->and($column->getAction())->not->toBeNull();

    $test->mountTableAction('viewCandidateSummary', $candidate)->assertSuccessful();

    // A silent no-op (e.g. calling ->hidden() on the very same Action
    // instance the "name" column's ->action() also uses — Action::isDisabled()
    // checks isHidden() internally, so a shared, hidden instance can never
    // actually mount) still returns a 200 response, so assertSuccessful()
    // alone doesn't prove the action opened. mountedActions is the real
    // signal Filament uses to render the slideover.
    expect($test->instance()->mountedActions)->not->toBeEmpty();
});

test('the quick view action is not disabled by a stray hidden() call sharing the name column\'s action instance', function () {
    // Filament's Action::isDisabled() checks isHidden() internally, so if
    // the shared $quickViewAction instance the "name" column's ->action()
    // uses is ever also registered elsewhere with ->hidden() (e.g. to keep
    // it off the row-actions dropdown), that hides — and so disables — the
    // one instance the name column depends on too. mountAction() then
    // silently no-ops on isDisabled(), even though the request still
    // succeeds. Guards directly against that regression.
    $candidate = makeHealthcareSearchCandidate();

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    $action = $test->instance()->getTable()->getAction('viewCandidateSummary');
    $action->record($candidate);

    expect($action->isDisabled())->toBeFalse();
});

test('Book, Set Week, and Save sit inside a single actions dropdown, Book and Save only visible when relevant', function () {
    $candidate = makeHealthcareSearchCandidate();

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    // Set Week has no visibility condition, so the dropdown always has at
    // least one item — this is also what keeps the "..." trigger itself
    // always on screen, per the user's request to retain it.
    $test->assertTableActionHidden('book', record: $candidate)
        ->assertTableActionHidden('saveWeekAvailability', record: $candidate)
        ->assertTableActionVisible('setWeekAvailability', record: $candidate);

    $test->call('handleDayClick', $candidate->id, 1);
    $test->assertTableActionVisible('book', record: $candidate);

    $test->call('stageAvailability', $candidate->id, 2, CandidateAvailabilityStatus::Available->value);
    $test->assertTableActionVisible('saveWeekAvailability', record: $candidate);
});

test('the Set Week bulk action fills in every unknown day for the visible week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $candidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->callTableAction('setWeekAvailability', $candidate, data: [
            'status' => CandidateAvailabilityStatus::AvailableAm->value,
        ]);

    $statuses = $candidate->availabilities()
        ->whereDate('date', '>=', $monday->toDateString())
        ->whereDate('date', '<=', $monday->copy()->addDays(4)->toDateString())
        ->pluck('status');

    expect($statuses)->toHaveCount(5)
        ->and($statuses->every(fn (CandidateAvailabilityStatus $status): bool => $status === CandidateAvailabilityStatus::AvailableAm))->toBeTrue();
});

test('the Set Week bulk action leaves already-set and booked days untouched', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $tuesday = $monday->copy()->addDay();
    $candidate = makeHealthcareSearchCandidate();

    $notAvailable = $candidate->availabilities()->create([
        'date' => $monday->toDateString(),
        'status' => CandidateAvailabilityStatus::NotAvailable->value,
    ]);

    $booking = $candidate->bookings()->create([
        'company_id' => $this->consultant->company_id,
        'client_id' => Client::factory()->create(['company_id' => $this->consultant->company_id])->id,
        'candidate_type' => HealthcareCandidate::class,
        'start_date' => $tuesday->toDateString(),
        'status' => BookingStatus::Upcoming,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->consultant->company_id,
        'date' => $tuesday->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->callTableAction('setWeekAvailability', $candidate, data: [
            'status' => CandidateAvailabilityStatus::Available->value,
        ]);

    expect($notAvailable->fresh()->status)->toBe(CandidateAvailabilityStatus::NotAvailable)
        ->and($candidate->availabilities()->whereDate('date', $tuesday->toDateString())->exists())->toBeFalse()
        ->and($candidate->availabilities()->whereDate('date', $monday->copy()->addDays(2)->toDateString())->first()->status)
        ->toBe(CandidateAvailabilityStatus::Available);
});

test('the week-commencing label sits above the day columns and follows navigation', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $nextMonday = $monday->copy()->addWeek();

    $test = Livewire::test(ListHealthcareCandidates::class)->set('activeSection', 'search');

    $test->assertSee('W/C '.$monday->format('d/m'));

    $test->call('goToNextWeek');

    $test->assertSee('W/C '.$nextMonday->format('d/m'))
        ->assertDontSee('W/C '.$monday->format('d/m'));
});
