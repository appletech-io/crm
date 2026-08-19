<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\CandidateAvailabilityStatus;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Client;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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

test('only the logged in consultants own candidates are returned, even for an admin', function () {
    $ownCandidate = makeHealthcareSearchCandidate();

    $otherConsultant = User::factory()->create(['company_id' => $this->consultant->company_id]);
    $otherConsultantCandidate = makeHealthcareSearchCandidate(['consultant_id' => $otherConsultant->id]);

    Livewire::test(ListHealthcareCandidates::class)
        ->set('activeSection', 'search')
        ->assertCanSeeTableRecords([$ownCandidate])
        ->assertCanNotSeeTableRecords([$otherConsultantCandidate]);
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

test('day filter excludes candidates booked on a selected day', function () {
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

    $freeCandidate = makeHealthcareSearchCandidate();

    Livewire::test(ListHealthcareCandidates::class)
        ->fillForm(['days' => [1]])
        ->set('activeSection', 'search')
        ->call('search')
        ->assertCanSeeTableRecords([$freeCandidate])
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
    $client = Client::factory()->create([
        'company_id' => $this->consultant->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'postcode' => null,
        'latitude' => 52.4862,
        'longitude' => -1.8904,
    ]);

    $nearby = makeHealthcareSearchCandidate(['latitude' => 52.4700, 'longitude' => -1.9000]);
    $farAway = makeHealthcareSearchCandidate(['latitude' => 51.5072, 'longitude' => -0.1276]);
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
