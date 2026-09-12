<?php

use App\Actions\Payroll\GenerateClientInvoices;
use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\InvoiceType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

function makeApprovedBooking(Company $company, Client $client, array $dayOverrides = [], array $bookingOverrides = []): Booking
{
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $booking = Booking::factory()->create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'status' => BookingStatus::Approved,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ], $bookingOverrides));

    $booking->dayPeriods()->create(array_merge([
        'company_id' => $company->id,
        'date' => Carbon::parse('2026-09-07')->toDateString(),
        'period' => BookingDayPeriod::FullDay->value,
        'approved_at' => now(),
    ], $dayOverrides));

    return $booking->fresh();
}

test('creates one invoice per client with approved days, with correct totals', function () {
    $company = Company::factory()->create();
    $clientA = Client::factory()->create(['company_id' => $company->id]);
    $clientB = Client::factory()->create(['company_id' => $company->id]);

    makeApprovedBooking($company, $clientA, [], ['day_rate' => 100, 'day_charge_rate' => 150]);
    makeApprovedBooking($company, $clientB, [], ['day_rate' => 100, 'day_charge_rate' => 200]);

    $start = Carbon::parse('2026-09-07')->startOfWeek();
    $end = Carbon::parse('2026-09-07')->endOfWeek();

    $results = GenerateClientInvoices::run($company, $start, $end);

    expect($results)->toHaveCount(2);

    $invoices = Invoice::where('company_id', $company->id)->get();
    expect($invoices)->toHaveCount(2)
        ->and($invoices->every(fn (Invoice $i) => $i->type === InvoiceType::Client))->toBeTrue()
        ->and($invoices->pluck('number')->unique())->toHaveCount(2);

    $clientAInvoice = $invoices->firstWhere('invoiceable_id', $clientA->id);
    expect($clientAInvoice->subtotal)->toBe(150.0)
        ->and($clientAInvoice->vat_amount)->toBe(30.0)
        ->and($clientAInvoice->total)->toBe(180.0)
        ->and($clientAInvoice->lines)->toHaveCount(1);

    $clientBInvoice = $invoices->firstWhere('invoiceable_id', $clientB->id);
    expect($clientBInvoice->subtotal)->toBe(200.0);
});

test('does not include unapproved or cancelled days', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);

    // Approved — included.
    makeApprovedBooking($company, $client);

    // Unapproved day.
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $unapproved = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);
    $unapproved->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => '2026-09-08',
        'period' => BookingDayPeriod::FullDay->value,
        'approved_at' => null,
    ]);

    $start = Carbon::parse('2026-09-07')->startOfWeek();
    $end = Carbon::parse('2026-09-07')->endOfWeek();

    $results = GenerateClientInvoices::run($company, $start, $end);

    expect($results)->toHaveCount(1);
    expect($results->first()['invoice']->lines)->toHaveCount(1);
});

test('generates a real PDF for each invoice', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    makeApprovedBooking($company, $client);

    $start = Carbon::parse('2026-09-07')->startOfWeek();
    $end = Carbon::parse('2026-09-07')->endOfWeek();

    $results = GenerateClientInvoices::run($company, $start, $end);

    expect($results->first()['pdf'])->toStartWith('%PDF');
    expect($results->first()['invoice']->pdf_path)->not->toBeNull();
});
