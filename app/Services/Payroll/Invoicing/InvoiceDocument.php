<?php

namespace App\Services\Payroll\Invoicing;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

/**
 * Storage helper for generated invoice PDFs — the app's existing Document
 * helper (app/Services/Candidates/Document.php) is candidate-scoped
 * (directoryFor() expects a candidate model), which doesn't fit a
 * company/client-level invoice, so this mirrors its putGeneratedViewable()
 * pattern one level up instead: write to the same disk config('filesystems.
 * default') resolves to (S3 in production), namespaced by company and
 * invoice type/number.
 */
class InvoiceDocument
{
    public static function store(Company $company, Invoice $invoice, string $pdfContents): string
    {
        $path = "{$company->id}/invoices/{$invoice->type->value}/{$invoice->number}.pdf";

        Storage::disk(config('filesystems.default'))->put($path, $pdfContents);

        return $path;
    }

    /**
     * Embeds the company logo as a base64 data URI so DomPDF can render it
     * without a network fetch — same approach as
     * ExportPayrollTimesheetsZipAction::logoDataUri().
     */
    public static function logoDataUri(Company $company): string
    {
        return "data:{$company->logoMimeType()};base64,".base64_encode($company->logoContents());
    }
}
