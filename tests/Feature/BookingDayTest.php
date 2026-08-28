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
