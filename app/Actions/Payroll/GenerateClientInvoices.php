<?php

namespace App\Actions\Payroll;

use App\Enums\InvoiceType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Payroll\Invoicing\ClientInvoiceData;
use App\Services\Payroll\Invoicing\InvoiceDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Creates one Invoice + its InvoiceLines per client with approved days this
 * period, renders a PDF for each, and returns them paired with the rendered
 * PDF bytes ready to zip — mirrors ExportPayrollTimesheetsZipAction's
 * per-client grouping and in-memory PDF rendering.
 */
class GenerateClientInvoices
{
    use AsAction;

    /** @return Collection<int, array{invoice: Invoice, pdf: string}> */
    public function handle(Company $company, Carbon $start, Carbon $end): Collection
    {
        return ClientInvoiceData::forPeriod($company, $start, $end)
            ->map(function (array $group) use ($company, $start, $end): array {
                /** @var Client $client */
                $client = $group['client'];

                $invoice = $this->createInvoice($company, $client, $group['lines'], $start, $end);
                $pdf = $this->renderPdf($invoice, $client, $company);

                $invoice->update(['pdf_path' => InvoiceDocument::store($company, $invoice, $pdf)]);

                return ['invoice' => $invoice->fresh('lines'), 'pdf' => $pdf];
            });
    }

    /** @param  array<int, array{description: string, quantity: float, unit_rate: float, amount: float, candidate_type: ?string, candidate_id: ?int}>  $lines */
    private function createInvoice(Company $company, Client $client, array $lines, Carbon $start, Carbon $end): Invoice
    {
        $subtotal = round(collect($lines)->sum('amount'), 2);
        $vat = round($subtotal * 0.2, 2);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'type' => InvoiceType::Client,
            'invoiceable_type' => Client::class,
            'invoiceable_id' => $client->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'total' => round($subtotal + $vat, 2),
            'generated_at' => now(),
        ]);

        // number is generated from the row's own id, so it can only be set
        // once the row (and its id) already exists — see Invoice::nextNumberFor().
        $invoice->update(['number' => Invoice::nextNumberFor($invoice->id)]);

        foreach ($lines as $line) {
            $invoice->lines()->create([
                'candidate_type' => $line['candidate_type'],
                'candidate_id' => $line['candidate_id'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_rate' => $line['unit_rate'],
                'amount' => $line['amount'],
            ]);
        }

        return $invoice;
    }

    private function renderPdf(Invoice $invoice, Client $client, Company $company): string
    {
        $html = view('pdfs.invoices.client', [
            'invoice' => $invoice,
            'client' => $client,
            'company' => $company,
            'logoDataUri' => InvoiceDocument::logoDataUri($company),
        ])->render();

        return Pdf::loadHTML($html)->output();
    }
}
