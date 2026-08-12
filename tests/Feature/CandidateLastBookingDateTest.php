<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Illuminate\Support\Carbon;

function createBookingFor(EducationCandidate|HealthcareCandidate $candidate, ?string $startDate, ?string $endDate): Booking
{
    $client = Client::factory()->create(['company_id' => $candidate->company_id]);

    return Booking::factory()->create([
        'company_id' => $candidate->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => $candidate::class,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);
}

test('last_booking_date is null for a candidate with no bookings', function () {
    $candidate = EducationCandidate::factory()->create();

    expect($candidate->last_booking_date)->toBeNull();
});

test('last_booking_date is the end date of the most recent booking', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    createBookingFor($candidate, '2026-01-01', '2026-01-05');
    createBookingFor($candidate, '2026-06-01', '2026-06-10');

    expect(Carbon::parse($candidate->last_booking_date)->toDateString())->toBe('2026-06-10');
});

test('last_booking_date falls back to the start date when the most recent booking has no end date', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    createBookingFor($candidate, '2026-01-01', '2026-01-05');
    createBookingFor($candidate, '2026-06-01', null);

    expect(Carbon::parse($candidate->last_booking_date)->toDateString())->toBe('2026-06-01');
});

test('last_booking_date works the same way for a healthcare candidate', function () {
    $company = Company::factory()->create();
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $company->id]);

    createBookingFor($candidate, '2026-03-01', '2026-03-15');

    expect(Carbon::parse($candidate->last_booking_date)->toDateString())->toBe('2026-03-15');
});

test('last_booking_date is exposed as a date-typed condition field for both candidate types', function () {
    $suggestions = EducationCandidate::candidateFieldSuggestions();
    expect($suggestions)->toHaveKey('last_booking_date');
    expect($suggestions['last_booking_date'])->toBe(['label' => 'Last Booking Date', 'type' => 'date']);

    $healthcareSuggestions = HealthcareCandidate::candidateFieldSuggestions();
    expect($healthcareSuggestions)->toHaveKey('last_booking_date');
    expect($healthcareSuggestions['last_booking_date'])->toBe(['label' => 'Last Booking Date', 'type' => 'date']);
});
