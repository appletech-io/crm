<?php

use App\Enums\BookingDayPeriod;
use App\Enums\PaymentMethod;
use App\Filament\Support\PayeExportCsvAction;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;

/** @return array<string, string> */
function payeCsvRowFor(BookingDay $dayPeriod): array
{
    $period = ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()];
    $response = PayeExportCsvAction::download(collect([$dayPeriod]), $period);

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    $rows = array_map('str_getcsv', explode("\n", trim($csv)));

    return array_combine($rows[0], $rows[1]);
}

test('an overnight session reports its actual elapsed hours, not the 24-hour complement', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'payment_method' => PaymentMethod::Paye->value,
    ]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'hourly_rate' => 15.00,
    ]);

    // 22:00 -> 07:00 is a 9-hour shift; time_from/time_to have no date
    // component, so naively diffing them same-day would give 15 hours
    // (24 - 9) instead.
    $dayPeriod = $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::Hours,
        'time_from' => '22:00',
        'time_to' => '07:00',
    ]);

    $row = payeCsvRowFor($dayPeriod->fresh());

    expect($row['Hours'])->toBe('9');
});
