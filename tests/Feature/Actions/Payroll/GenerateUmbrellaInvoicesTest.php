<?php

use App\Actions\Payroll\GenerateUmbrellaInvoices;
use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Invoice;
use App\Models\PaymentProvider;
use Illuminate\Support\Carbon;

test('creates one self-bill invoice per umbrella company, only for umbrella-paid candidates', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $provider = PaymentProvider::factory()->create(['company_id' => $company->id, 'vat_reg_number' => 'GB123456789']);

    $umbrellaCandidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'payment_method' => PaymentMethod::Umbrella,
        'payment_provider_id' => $provider->id,
    ]);
    $payeCandidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'payment_method' => PaymentMethod::Paye,
    ]);

    foreach ([$umbrellaCandidate, $payeCandidate] as $candidate) {
        $booking = Booking::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'candidate_id' => $candidate->id,
            'candidate_type' => EducationCandidate::class,
            'status' => BookingStatus::Approved,
            'day_rate' => 100,
            'day_charge_rate' => 150,
        ]);
        $booking->dayPeriods()->create([
            'company_id' => $company->id,
            'date' => '2026-09-07',
            'period' => BookingDayPeriod::FullDay->value,
            'approved_at' => now(),
        ]);
    }

    $start = Carbon::parse('2026-09-07')->startOfWeek();
    $end = Carbon::parse('2026-09-07')->endOfWeek();

    $results = GenerateUmbrellaInvoices::run($company, $start, $end);

    expect($results)->toHaveCount(1);

    $invoice = Invoice::where('company_id', $company->id)->first();
    expect($invoice->type)->toBe(InvoiceType::Umbrella)
        ->and($invoice->invoiceable_id)->toBe($provider->id)
        ->and($invoice->subtotal)->toBe(100.0) // pay rate, not charge rate
        ->and($invoice->vat_amount)->toBe(20.0) // provider is VAT registered
        ->and($invoice->lines)->toHaveCount(1);
});

test('does not charge VAT when the umbrella company has no VAT registration number', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $provider = PaymentProvider::factory()->create(['company_id' => $company->id, 'vat_reg_number' => null]);

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
    $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => '2026-09-07',
        'period' => BookingDayPeriod::FullDay->value,
        'approved_at' => now(),
    ]);

    $start = Carbon::parse('2026-09-07')->startOfWeek();
    $end = Carbon::parse('2026-09-07')->endOfWeek();

    $results = GenerateUmbrellaInvoices::run($company, $start, $end);

    expect($results->first()['invoice']->vat_amount)->toBe(0.0);
});
