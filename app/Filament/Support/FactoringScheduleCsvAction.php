<?php

namespace App\Filament\Support;

use App\Models\Client;
use App\Models\Company;
use App\Services\Payroll\Invoicing\ClientInvoiceData;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Export Factoring Schedule" header action on the Invoicing page — a
 * manifest of this period's client invoice totals (client, subtotal, VAT,
 * total), the kind of schedule a finance factoring company needs to know
 * what's being assigned to them. Derived directly from ClientInvoiceData
 * rather than requiring "Generate Client Invoices" to have been run first,
 * so the two buttons on the page don't depend on click order.
 */
class FactoringScheduleCsvAction
{
    private const array HEADINGS = [
        'Client', 'Period Start', 'Period End', 'Subtotal', 'VAT', 'Total',
    ];

    /**
     * @param  Closure(): Company  $company
     * @param  Closure(): array{start: Carbon, end: Carbon}  $period
     */
    public static function header(Closure $company, Closure $period): Action
    {
        return Action::make('exportFactoringSchedule')
            ->label('Export Factoring Schedule')
            ->icon(Heroicon::OutlinedBuildingLibrary)
            ->color('gray')
            ->action(fn (): StreamedResponse => static::download($company(), $period()));
    }

    /** @param  array{start: Carbon, end: Carbon}  $period */
    public static function download(Company $company, array $period): StreamedResponse
    {
        $filename = 'factoring-schedule-'.$period['start']->toDateString().'-to-'.$period['end']->toDateString().'.csv';

        $groups = ClientInvoiceData::forPeriod($company, $period['start'], $period['end']);

        return response()->streamDownload(function () use ($groups, $period): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, self::HEADINGS);

            foreach ($groups as $group) {
                fputcsv($handle, static::row($group, $period));
            }

            fclose($handle);
        }, $filename);
    }

    /**
     * @param  array{client: Client, lines: array<int, array{amount: float}>}  $group
     * @param  array{start: Carbon, end: Carbon}  $period
     * @return array<int, mixed>
     */
    private static function row(array $group, array $period): array
    {
        $subtotal = round(collect($group['lines'])->sum('amount'), 2);
        $vat = round($subtotal * 0.2, 2);

        return [
            $group['client']->name,
            $period['start']->toDateString(),
            $period['end']->toDateString(),
            number_format($subtotal, 2, '.', ''),
            number_format($vat, 2, '.', ''),
            number_format($subtotal + $vat, 2, '.', ''),
        ];
    }
}
