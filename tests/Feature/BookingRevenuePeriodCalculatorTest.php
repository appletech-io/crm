<?php

use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Reporting\BookingRevenuePeriodCalculator;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->company = $this->user->company;
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
});

function createReportBookingWithDay(
    User $user,
    Client $client,
    JobTitle $jobTitle,
    string $date,
    array $bookingAttributes = [],
    array $dayAttributes = [],
): Booking {
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

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

test('totals sums revenue, cost and margin across the range and excludes cancelled days', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $start = Carbon::parse('2026-01-01');

    createReportBookingWithDay($this->user, $client, $this->jobTitle, '2026-01-05', [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    createReportBookingWithDay($this->user, $client, $this->jobTitle, '2026-01-12', [
        'day_rate' => 80,
        'day_charge_rate' => 120,
    ]);

    createReportBookingWithDay($this->user, $client, $this->jobTitle, '2026-01-15', [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ], [
        'cancelled_at' => now(),
    ]);

    $totals = BookingRevenuePeriodCalculator::totals($start, Carbon::parse('2026-01-31'));

    expect($totals['bookings'])->toBe(2)
        ->and($totals['revenue'])->toBe(270.0)
        ->and($totals['cost'])->toBe(180.0)
        ->and($totals['margin'])->toBe(90.0)
        ->and($totals['avgMargin'])->toBe(0.3333);
});

test('byWeek buckets revenue into the correct weeks', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);

    createReportBookingWithDay($this->user, $client, $this->jobTitle, '2026-01-05', [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    createReportBookingWithDay($this->user, $client, $this->jobTitle, '2026-01-12', [
        'day_rate' => 80,
        'day_charge_rate' => 120,
    ]);

    $weeks = BookingRevenuePeriodCalculator::byWeek(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($weeks)->toHaveCount(2)
        ->and($weeks[0]['revenue'])->toBe(150.0)
        ->and($weeks[1]['revenue'])->toBe(120.0);
});

test('a client filter excludes other clients bookings', function () {
    $clientA = Client::factory()->create(['company_id' => $this->company->id]);
    $clientB = Client::factory()->create(['company_id' => $this->company->id]);

    createReportBookingWithDay($this->user, $clientA, $this->jobTitle, '2026-01-05', [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    createReportBookingWithDay($this->user, $clientB, $this->jobTitle, '2026-01-06', [
        'day_rate' => 80,
        'day_charge_rate' => 120,
    ]);

    $totals = BookingRevenuePeriodCalculator::totals(
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
        clientId: $clientA->id,
    );

    expect($totals['bookings'])->toBe(1)
        ->and($totals['revenue'])->toBe(150.0);
});

test('a consultant filter excludes other consultants bookings', function () {
    $consultantA = User::factory()->create(['company_id' => $this->company->id]);
    $consultantB = User::factory()->create(['company_id' => $this->company->id]);
    $client = Client::factory()->create(['company_id' => $this->company->id]);

    createReportBookingWithDay($this->user, $client, $this->jobTitle, '2026-01-05', [
        'consultant_id' => $consultantA->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    createReportBookingWithDay($this->user, $client, $this->jobTitle, '2026-01-06', [
        'consultant_id' => $consultantB->id,
        'day_rate' => 80,
        'day_charge_rate' => 120,
    ]);

    $totals = BookingRevenuePeriodCalculator::totals(
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
        consultantId: $consultantA->id,
    );

    expect($totals['bookings'])->toBe(1)
        ->and($totals['revenue'])->toBe(150.0);
});

test('byBooking returns one row per booking with its own revenue, cost and margin', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Acme Ltd']);
    $consultant = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Jo Consultant']);

    $booking = createReportBookingWithDay($this->user, $client, $this->jobTitle, '2026-01-05', [
        'consultant_id' => $consultant->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    $rows = BookingRevenuePeriodCalculator::byBooking(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['bookingId'])->toBe($booking->id)
        ->and($rows[0]['clientName'])->toBe('Acme Ltd')
        ->and($rows[0]['consultantName'])->toBe('Jo Consultant')
        ->and($rows[0]['revenue'])->toBe(150.0)
        ->and($rows[0]['cost'])->toBe(100.0)
        ->and($rows[0]['margin'])->toBe(50.0)
        ->and($rows[0]['days'])->toBe(1);
});

test('byClient ranks clients by revenue descending', function () {
    $bigClient = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Big Client']);
    $smallClient = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Small Client']);

    createReportBookingWithDay($this->user, $smallClient, $this->jobTitle, '2026-01-05', [
        'day_rate' => 50,
        'day_charge_rate' => 80,
    ]);

    createReportBookingWithDay($this->user, $bigClient, $this->jobTitle, '2026-01-06', [
        'day_rate' => 100,
        'day_charge_rate' => 200,
    ]);

    $ranked = BookingRevenuePeriodCalculator::byClient(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($ranked)->toHaveCount(2)
        ->and($ranked[0]['clientName'])->toBe('Big Client')
        ->and($ranked[0]['revenue'])->toBe(200.0)
        ->and($ranked[1]['clientName'])->toBe('Small Client');
});
