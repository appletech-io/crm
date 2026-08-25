<?php

use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Reporting\ConsultantPerformanceSummary;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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

function createSummaryBooking(
    User $consultant,
    Client $client,
    EducationCandidate $candidate,
    JobTitle $jobTitle,
    string $date,
    array $bookingAttributes = [],
    array $dayAttributes = [],
): Booking {
    $booking = Booking::factory()->create(array_merge([
        'company_id' => $consultant->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'consultant_id' => $consultant->id,
    ], $bookingAttributes));

    $booking->dayPeriods()->create(array_merge([
        'company_id' => $consultant->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ], $dayAttributes));

    return $booking;
}

test('forWeek counts distinct clients, candidates, days placed, and gp for the given week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    createSummaryBooking($this->user, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);
    createSummaryBooking($this->user, $client, $candidate, $this->jobTitle, $monday->copy()->addDay()->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    $stats = ConsultantPerformanceSummary::forWeek($this->user->id, $monday);

    expect($stats['clients'])->toBe(1)
        ->and($stats['candidates'])->toBe(1)
        ->and($stats['daysPlaced'])->toBe(2)
        ->and($stats['gp'])->toBe(100.0)
        ->and($stats['avgMargin'])->toBe(0.3333);
});

test('forWeek excludes cancelled days and only counts the given consultant', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);

    createSummaryBooking($this->user, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);
    createSummaryBooking($this->user, $client, $candidate, $this->jobTitle, $monday->copy()->addDay()->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ], [
        'cancelled_at' => now(),
    ]);
    createSummaryBooking($otherConsultant, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    $stats = ConsultantPerformanceSummary::forWeek($this->user->id, $monday);

    expect($stats['daysPlaced'])->toBe(1);
});

test('forWeek with a null consultant id includes every consultant', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);

    createSummaryBooking($this->user, $client, $candidate, $this->jobTitle, $monday->toDateString());
    createSummaryBooking($otherConsultant, $client, $candidate, $this->jobTitle, $monday->copy()->addDay()->toDateString());

    $stats = ConsultantPerformanceSummary::forWeek(null, $monday);

    expect($stats['daysPlaced'])->toBe(2);
});

test('rebookRate is next weeks days placed as a percentage of this weeks', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    // 5 days this week...
    foreach (range(0, 4) as $offset) {
        createSummaryBooking($this->user, $client, $candidate, $this->jobTitle, $monday->copy()->addDays($offset)->toDateString());
    }

    // ...4 days already booked next week = 80%.
    $nextMonday = $monday->copy()->addWeek();
    foreach (range(0, 3) as $offset) {
        createSummaryBooking($this->user, $client, $candidate, $this->jobTitle, $nextMonday->copy()->addDays($offset)->toDateString());
    }

    $rate = ConsultantPerformanceSummary::rebookRate($this->user->id, $monday);

    expect($rate)->toBe(80.0);
});

test('rebookRate is null when nothing was booked this week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $rate = ConsultantPerformanceSummary::rebookRate($this->user->id, $monday);

    expect($rate)->toBeNull();
});

test('rebookRate is 0 when nothing has been booked next week yet', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    createSummaryBooking($this->user, $client, $candidate, $this->jobTitle, $monday->toDateString());

    $rate = ConsultantPerformanceSummary::rebookRate($this->user->id, $monday);

    expect($rate)->toBe(0.0);
});
