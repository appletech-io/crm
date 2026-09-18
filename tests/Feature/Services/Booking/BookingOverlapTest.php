<?php

use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Services\Booking\BookingOverlap;

function makeExistingBooking(Company $company, EducationCandidate $candidate, string $date, BookingDayPeriod $period): Booking
{
    $client = Client::factory()->create(['company_id' => $company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => $date,
        'period' => $period,
    ]);

    return $booking;
}

test('a Sleep-In always clashes with another Sleep-In on the same date', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $date = now()->toDateString();

    makeExistingBooking($company, $candidate, $date, BookingDayPeriod::SleepIn);

    $conflicts = BookingOverlap::conflictingDates(
        EducationCandidate::class,
        $candidate->id,
        [['date' => $date, 'period' => BookingDayPeriod::SleepIn->value]],
    );

    expect($conflicts->all())->toBe([$date]);
});

test('a Waking Night always clashes with a daytime AM booking on the same date', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $date = now()->toDateString();

    makeExistingBooking($company, $candidate, $date, BookingDayPeriod::Am);

    $conflicts = BookingOverlap::conflictingDates(
        EducationCandidate::class,
        $candidate->id,
        [['date' => $date, 'period' => BookingDayPeriod::WakingNight->value]],
    );

    expect($conflicts->all())->toBe([$date]);
});

test('Sleep-In and Waking Night on the same date for the same candidate clash with each other', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $date = now()->toDateString();

    makeExistingBooking($company, $candidate, $date, BookingDayPeriod::WakingNight);

    $conflicts = BookingOverlap::conflictingDates(
        EducationCandidate::class,
        $candidate->id,
        [['date' => $date, 'period' => BookingDayPeriod::SleepIn->value]],
    );

    expect($conflicts->all())->toBe([$date]);
});
