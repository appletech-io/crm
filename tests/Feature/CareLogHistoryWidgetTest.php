<?php

use App\Enums\BookingDayPeriod;
use App\Enums\Healthcare\Wellbeing;
use App\Filament\Widgets\CareLogHistory;
use App\Models\Booking;
use App\Models\CareLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Oakwood Care Home']);
    $this->candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
});

function makeLoggedShift(Booking $booking, array $overrides = []): CareLog
{
    $day = $booking->dayPeriods()->create(array_merge([
        'company_id' => $booking->company_id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ], $overrides));

    return CareLog::factory()->create([
        'company_id' => $booking->company_id,
        'booking_day_id' => $day->id,
        'wellbeing' => Wellbeing::Good->value,
        'care_provided' => 'Assisted with daily routine.',
    ]);
}

test('scoped to a booking, it only shows that booking\'s care logs', function () {
    $bookingA = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);
    $bookingB = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    makeLoggedShift($bookingA, ['period' => BookingDayPeriod::FullDay]);
    makeLoggedShift($bookingB, ['period' => BookingDayPeriod::SleepIn]);

    Livewire::test(CareLogHistory::class, ['record' => $bookingA])
        ->assertSuccessful()
        ->assertSee('Full Day')
        ->assertDontSee('Sleep-In');
});

test('scoped to a candidate, it shows care logs across every one of their bookings', function () {
    $bookingA = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);
    $otherClient = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Riverside Home']);
    $bookingB = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $otherClient->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    makeLoggedShift($bookingA);
    makeLoggedShift($bookingB);

    Livewire::test(CareLogHistory::class, ['record' => $this->candidate])
        ->assertSuccessful()
        ->assertSee('Oakwood Care Home')
        ->assertSee('Riverside Home');
});

test('a different candidate\'s care logs never show up on this candidate\'s history', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);
    makeLoggedShift($booking);

    $otherCandidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);

    Livewire::test(CareLogHistory::class, ['record' => $otherCandidate])
        ->assertSuccessful()
        ->assertSee('No care logs yet.');
});

test('with no record bound, it shows an empty state rather than every log', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);
    makeLoggedShift($booking);

    Livewire::test(CareLogHistory::class)
        ->assertSuccessful()
        ->assertSee('No care logs yet.');
});
