<?php

use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;

test('payRate resolves the correct booking rate field for each period', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'day_rate' => 150.00,
        'half_day_rate' => 80.00,
        'hourly_rate' => 18.00,
    ]);

    $fullDay = $booking->dayPeriods()->create(['company_id' => $company->id, 'date' => now()->toDateString(), 'period' => BookingDayPeriod::FullDay]);
    $halfDay = $booking->dayPeriods()->create(['company_id' => $company->id, 'date' => now()->addDay()->toDateString(), 'period' => BookingDayPeriod::Pm]);
    $hoursDay = $booking->dayPeriods()->create(['company_id' => $company->id, 'date' => now()->addDays(2)->toDateString(), 'period' => BookingDayPeriod::Hours]);

    expect($fullDay->payRate())->toBe(150.00)
        ->and($halfDay->payRate())->toBe(80.00)
        ->and($hoursDay->payRate())->toBe(18.00);
});

test('payRate and chargeRate resolve Sleep-In and Waking Night to their own rate fields', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'sleep_in_rate' => 45.00,
        'sleep_in_charge_rate' => 65.00,
        'waking_night_rate' => 14.50,
        'waking_night_charge_rate' => 22.00,
    ]);

    $sleepIn = $booking->dayPeriods()->create(['company_id' => $company->id, 'date' => now()->toDateString(), 'period' => BookingDayPeriod::SleepIn]);
    $wakingNight = $booking->dayPeriods()->create(['company_id' => $company->id, 'date' => now()->addDay()->toDateString(), 'period' => BookingDayPeriod::WakingNight]);

    expect($sleepIn->payRate())->toBe(45.00)
        ->and($sleepIn->chargeRate())->toBe(65.00)
        ->and($wakingNight->payRate())->toBe(14.50)
        ->and($wakingNight->chargeRate())->toBe(22.00);
});
