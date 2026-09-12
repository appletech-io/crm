<?php

namespace App\Filament\Support;

use App\Enums\PaymentMethod;
use App\Models\BookingDay;
use App\Models\PaymentProvider;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Export HMRC Report" header action on the Invoicing page — a placeholder
 * shaped after HMRC's real quarterly Employment Intermediaries Report
 * (payments made to workers supplied via an intermediary, e.g. an umbrella
 * company, where this agency didn't operate PAYE on them). Real HMRC
 * submissions are quarterly, not per payroll period — this exports whatever
 * period is currently selected on the page as a starting point; the exact
 * column set/format hasn't been confirmed against HMRC's actual template
 * yet, so treat this as unverified until it has.
 */
class HmrcIntermediariesReportCsvAction
{
    private const array HEADINGS = [
        'Worker First Name', 'Worker Last Name', 'Worker Date of Birth', 'Worker Gender',
        'Worker NI Number', 'Worker Address', 'Worker Postcode',
        'Engagement Start Date', 'Engagement End Date', 'Number of Payments', 'Total Payments',
        'Intermediary Name', 'Intermediary Address', 'Intermediary Company Number',
    ];

    /**
     * @param  Closure(): Collection<int, BookingDay>  $dayPeriods
     * @param  Closure(): array{start: Carbon, end: Carbon}  $period
     */
    public static function header(Closure $dayPeriods, Closure $period): Action
    {
        return Action::make('exportHmrcReport')
            ->label('Export HMRC Report')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->action(fn (): StreamedResponse => static::download($dayPeriods(), $period()));
    }

    /**
     * @param  Collection<int, BookingDay>  $dayPeriods
     * @param  array{start: Carbon, end: Carbon}  $period
     */
    public static function download(Collection $dayPeriods, array $period): StreamedResponse
    {
        $filename = 'hmrc-intermediaries-report-'.$period['start']->toDateString().'-to-'.$period['end']->toDateString().'.csv';

        $groups = $dayPeriods
            ->filter(function (BookingDay $day): bool {
                $candidate = $day->booking?->candidate;

                return $candidate
                    && method_exists($candidate, 'paymentProvider')
                    && $candidate->payment_method === PaymentMethod::Umbrella
                    && $candidate->payment_provider_id;
            })
            ->groupBy(fn (BookingDay $day): string => "{$day->booking->candidate_type}|{$day->booking->candidate_id}");

        return response()->streamDownload(function () use ($groups): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, self::HEADINGS);

            foreach ($groups as $days) {
                fputcsv($handle, static::row($days));
            }

            fclose($handle);
        }, $filename);
    }

    /** @param  Collection<int, BookingDay>  $days */
    private static function row(Collection $days): array
    {
        $sorted = $days->sortBy('date')->values();
        $candidate = $sorted->first()->booking->candidate;
        /** @var PaymentProvider $provider */
        $provider = $candidate->paymentProvider;

        $totalPay = $days->sum(fn (BookingDay $day): float => $day->isCancelled() ? 0 : ($day->payRate() ?? 0));

        return [
            $candidate->first_name ?? '',
            $candidate->last_name ?? '',
            $candidate->date_of_birth?->toDateString() ?? '',
            $candidate->gender ?? '',
            $candidate->ni_number ?? '',
            $candidate->address ?? '',
            $candidate->postcode ?? '',
            $sorted->first()->date->toDateString(),
            $sorted->last()->date->toDateString(),
            $days->count(),
            number_format($totalPay, 2, '.', ''),
            $provider->name,
            trim(collect([$provider->address_1, $provider->address_2, $provider->postcode])->filter()->implode(', ')),
            $provider->company_reg_number ?? '',
        ];
    }
}
