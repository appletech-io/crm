<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Resources\Bookings\Widgets\BookingWeekStats;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);

    $this->company = $this->user->company;

    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
});

function createBookingWithDay(
    User $user,
    Client $client,
    EducationCandidate $candidate,
    JobTitle $jobTitle,
    string $date,
    array $bookingAttributes = [],
    array $dayAttributes = [],
): Booking {
    $booking = Booking::factory()->create(array_merge([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
    ], $bookingAttributes));

    $booking->dayPeriods()->create(array_merge([
        'company_id' => $user->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ], $dayAttributes));

    return $booking;
}

test('it counts distinct clients, candidates, days placed, and computes gp for the current week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $clientA = Client::factory()->create(['company_id' => $this->company->id]);
    $clientB = Client::factory()->create(['company_id' => $this->company->id]);
    $candidateA = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $candidateB = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    createBookingWithDay($this->user, $clientA, $candidateA, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    createBookingWithDay($this->user, $clientA, $candidateA, $this->jobTitle, $monday->copy()->addDay()->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    createBookingWithDay($this->user, $clientB, $candidateB, $this->jobTitle, $monday->copy()->addDays(2)->toDateString(), [
        'day_rate' => 80,
        'day_charge_rate' => 120,
    ]);

    $component = Livewire::test(BookingWeekStats::class);
    $stats = $component->instance()->weekStats();

    expect($stats['clients'])->toBe(2)
        ->and($stats['candidates'])->toBe(2)
        ->and($stats['daysPlaced'])->toBe(3)
        ->and($stats['gp'])->toBe(140.0)
        ->and($stats['avgMargin'])->toBe(0.3333);
});

test('cancelled days do not count towards days placed or gp', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    createBookingWithDay($this->user, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    createBookingWithDay($this->user, $client, $candidate, $this->jobTitle, $monday->copy()->addDay()->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ], [
        'cancelled_at' => now(),
    ]);

    $stats = Livewire::test(BookingWeekStats::class)->instance()->weekStats();

    expect($stats['daysPlaced'])->toBe(1)
        ->and($stats['gp'])->toBe(50.0)
        ->and($stats['avgMargin'])->toBe(0.3333);
});

test('am and pm sessions each count as a full day placed', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'half_day_rate' => 50,
        'half_day_charge_rate' => 80,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => $monday->toDateString(),
        'period' => BookingDayPeriod::Am,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => $monday->copy()->addDay()->toDateString(),
        'period' => BookingDayPeriod::Pm,
    ]);

    $stats = Livewire::test(BookingWeekStats::class)->instance()->weekStats();

    expect($stats['daysPlaced'])->toBe(2)
        ->and($stats['gp'])->toBe(60.0);
});

test('a non-admin consultant only sees their own bookings in the stats', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $ownCandidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultant->id]);
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $this->user->id]);

    $monday = now()->startOfWeek(Carbon::MONDAY);

    createBookingWithDay($this->user, $client, $ownCandidate, $this->jobTitle, $monday->toDateString(), [
        'consultant_id' => $consultant->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    createBookingWithDay($this->user, $client, $otherCandidate, $this->jobTitle, $monday->toDateString(), [
        'consultant_id' => $this->user->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", 1);

    $stats = Livewire::test(BookingWeekStats::class)->instance()->weekStats();

    expect($stats['daysPlaced'])->toBe(1);
});

test('an admin can filter the stats down to a single consultant', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantB->assignRole('consultant');

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidateA = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $candidateB = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $monday = now()->startOfWeek(Carbon::MONDAY);

    createBookingWithDay($this->user, $client, $candidateA, $this->jobTitle, $monday->toDateString(), [
        'consultant_id' => $consultantA->id,
    ]);

    createBookingWithDay($this->user, $client, $candidateB, $this->jobTitle, $monday->toDateString(), [
        'consultant_id' => $consultantB->id,
    ]);

    $component = Livewire::test(BookingWeekStats::class);
    expect($component->instance()->weekStats()['daysPlaced'])->toBe(2);

    $component->set('consultantId', $consultantA->id);
    expect($component->instance()->weekStats()['daysPlaced'])->toBe(1);
});

test('the consultant filter select is only shown to admins', function () {
    Livewire::test(BookingWeekStats::class)
        ->assertSee('All Consultants');

    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", 1);

    Livewire::test(BookingWeekStats::class)
        ->assertDontSee('All Consultants');
});
