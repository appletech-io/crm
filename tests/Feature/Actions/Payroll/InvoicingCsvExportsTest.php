<?php

use App\Enums\BookingDayPeriod;
use App\Enums\PaymentMethod;
use App\Filament\Support\FactoringScheduleCsvAction;
use App\Filament\Support\HmrcIntermediariesReportCsvAction;
use App\Filament\Support\PayeExportCsvAction;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\PaymentProvider;
use Illuminate\Support\Carbon;

function makeCandidateDay(Company $company, Client $client, array $candidateOverrides = []): BookingDay
{
    $candidate = EducationCandidate::factory()->create(array_merge([
        'company_id' => $company->id,
    ], $candidateOverrides));

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    return $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => '2026-09-07',
        'period' => BookingDayPeriod::FullDay->value,
        'approved_at' => now(),
    ]);
}

test('factoring schedule lists one row per client with correct totals', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Acme School']);
    makeCandidateDay($company, $client);

    $period = ['start' => Carbon::parse('2026-09-07')->startOfWeek(), 'end' => Carbon::parse('2026-09-07')->endOfWeek()];

    $response = FactoringScheduleCsvAction::download($company, $period);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Acme School')
        ->and($csv)->toContain('150.00')
        ->and($csv)->toContain('30.00')
        ->and($csv)->toContain('180.00');
});

test('PAYE export only includes PAYE candidates', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $payeDay = makeCandidateDay($company, $client, ['payment_method' => PaymentMethod::Paye, 'first_name' => 'Pat', 'last_name' => 'Paye']);

    $provider = PaymentProvider::factory()->create(['company_id' => $company->id]);
    $umbrellaDay = makeCandidateDay($company, $client, ['payment_method' => PaymentMethod::Umbrella, 'payment_provider_id' => $provider->id, 'first_name' => 'Uma', 'last_name' => 'Umbrella']);

    $days = collect([$payeDay, $umbrellaDay]);
    $period = ['start' => Carbon::parse('2026-09-07')->startOfWeek(), 'end' => Carbon::parse('2026-09-07')->endOfWeek()];

    $response = PayeExportCsvAction::download($days, $period);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Pat Paye')
        ->and($csv)->not->toContain('Uma Umbrella');
});

test('HMRC report only includes umbrella-paid candidates, aggregated per candidate', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $provider = PaymentProvider::factory()->create(['company_id' => $company->id, 'name' => 'Giant Umbrella Ltd']);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'payment_method' => PaymentMethod::Umbrella,
        'payment_provider_id' => $provider->id,
    ]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    $day1 = $booking->dayPeriods()->create(['company_id' => $company->id, 'date' => '2026-09-07', 'period' => BookingDayPeriod::FullDay->value, 'approved_at' => now()]);
    $day2 = $booking->dayPeriods()->create(['company_id' => $company->id, 'date' => '2026-09-08', 'period' => BookingDayPeriod::FullDay->value, 'approved_at' => now()]);

    $payeCandidate = EducationCandidate::factory()->create(['company_id' => $company->id, 'payment_method' => PaymentMethod::Paye]);
    $payeBooking = Booking::factory()->create(['company_id' => $company->id, 'client_id' => $client->id, 'candidate_id' => $payeCandidate->id, 'candidate_type' => EducationCandidate::class, 'day_rate' => 100, 'day_charge_rate' => 150]);
    $payeDay = $payeBooking->dayPeriods()->create(['company_id' => $company->id, 'date' => '2026-09-07', 'period' => BookingDayPeriod::FullDay->value, 'approved_at' => now()]);

    $days = collect([$day1->fresh(), $day2->fresh(), $payeDay->fresh()]);
    $period = ['start' => Carbon::parse('2026-09-07')->startOfWeek(), 'end' => Carbon::parse('2026-09-07')->endOfWeek()];

    $response = HmrcIntermediariesReportCsvAction::download($days, $period);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    $lines = array_filter(explode("\n", trim($csv)));

    expect($lines)->toHaveCount(2) // header + one aggregated candidate row
        ->and($csv)->toContain('Giant Umbrella Ltd')
        ->and($csv)->toContain('200.00'); // 2 days x £100 pay rate
});
