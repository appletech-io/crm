<?php

namespace App\Actions\Payroll;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PaymentProvider;
use App\Services\Payroll\Invoicing\InvoiceDocument;
use App\Services\Payroll\Invoicing\UmbrellaInvoiceData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Creates one self-bill Invoice + its InvoiceLines per umbrella company
 * (PaymentProvider) with approved umbrella-paid candidate days this period —
 * see UmbrellaInvoiceData's docblock for why this is a self-bill rather than
 * an invoice the umbrella sends the agency. Mirrors GenerateClientInvoices.
 */
class GenerateUmbrellaInvoices
{
    use AsAction;

    /** @return Collection<int, array{invoice: Invoice, pdf: string}> */
    public function handle(Company $company, Carbon $start, Carbon $end): Collection
    {
        return UmbrellaInvoiceData::forPeriod($company, $start, $end)
            ->map(function (array $group) use ($company, $start, $end): array {
                /** @var PaymentProvider $provider */
                $provider = $group['provider'];

                $invoice = $this->createInvoice($company, $provider, $group['lines'], $start, $end);
                $pdf = $this->renderPdf($invoice, $provider, $company);

                $invoice->update(['pdf_path' => InvoiceDocument::store($company, $invoice, $pdf)]);

                return ['invoice' => $invoice->fresh('lines'), 'pdf' => $pdf];
            });
    }

    /** @param  array<int, array{description: string, quantity: float, unit_rate: float, amount: float, candidate_type: ?string, candidate_id: ?int}>  $lines */
    private function createInvoice(Company $company, PaymentProvider $provider, array $lines, Carbon $start, Carbon $end): Invoice
    {
        $subtotal = round(collect($lines)->sum('amount'), 2);
        // Only VAT this self-bill if the umbrella itself is VAT registered —
        // unlike client invoices, this isn't a flat default.
        $vat = filled($provider->vat_reg_number) ? round($subtotal * 0.2, 2) : 0.0;

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'type' => InvoiceType::Umbrella,
            'invoiceable_type' => PaymentProvider::class,
            'invoiceable_id' => $provider->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'total' => round($subtotal + $vat, 2),
            'generated_at' => now(),
        ]);

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

    private function renderPdf(Invoice $invoice, PaymentProvider $provider, Company $company): string
    {
        $html = view('pdfs.invoices.umbrella', [
            'invoice' => $invoice,
            'provider' => $provider,
            'company' => $company,
            'logoDataUri' => InvoiceDocument::logoDataUri($company),
        ])->render();

        return Pdf::loadHTML($html)->output();
    }
}
