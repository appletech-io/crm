<?php

namespace App\Filament\Support;

use App\Enums\PaymentMethod;
use App\Models\BookingDay;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Export PAYE File" header action on the Invoicing page — a generic
 * PAYE-candidate payroll-run file, shaped for import into Sage/BrightPay.
 * The exact column set those systems expect isn't known yet (no real
 * template to build against), so this is a best-effort placeholder covering
 * what this app actually holds — flagged here for whoever supplies the real
 * template later.
 */
class PayeExportCsvAction
{
    private const array HEADINGS = [
        'Candidate', 'First Name', 'Last Name', 'Date of Birth', 'Gender', 'NI Number',
        'Address', 'City', 'County', 'Postcode',
        'Date', 'Session', 'Hours', 'Pay Rate', 'Gross Pay',
        'Bank Account Name', 'Bank Account Number', 'Sort Code',
    ];

    /**
     * @param  Closure(): Collection<int, BookingDay>  $dayPeriods
     * @param  Closure(): array{start: Carbon, end: Carbon}  $period
     */
    public static function header(Closure $dayPeriods, Closure $period): Action
    {
        return Action::make('exportPayeFile')
            ->label('Export PAYE File')
            ->icon(Heroicon::OutlinedDocumentCurrencyPound)
            ->color('gray')
            ->action(fn (): StreamedResponse => static::download($dayPeriods(), $period()));
    }

    /**
     * @param  Collection<int, BookingDay>  $dayPeriods
     * @param  array{start: Carbon, end: Carbon}  $period
     */
    public static function download(Collection $dayPeriods, array $period): StreamedResponse
    {
        $filename = 'paye-'.$period['start']->toDateString().'-to-'.$period['end']->toDateString().'.csv';

        $payeDays = $dayPeriods->filter(function (BookingDay $day): bool {
            $candidate = $day->booking?->candidate;

            return $candidate && $candidate->payment_method === PaymentMethod::Paye;
        });

        return response()->streamDownload(function () use ($payeDays): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, self::HEADINGS);

            foreach ($payeDays as $dayPeriod) {
                fputcsv($handle, static::row($dayPeriod));
            }

            fclose($handle);
        }, $filename);
    }

    /** @return array<int, mixed> */
    private static function row(BookingDay $dayPeriod): array
    {
        $candidate = $dayPeriod->booking?->candidate;
        $payRate = $dayPeriod->isCancelled() ? null : $dayPeriod->payRate();

        return [
            static::candidateName($candidate),
            $candidate->first_name ?? '',
            $candidate->last_name ?? '',
            $candidate->date_of_birth?->toDateString() ?? '',
            $candidate->gender ?? '',
            $candidate->ni_number ?? '',
            $candidate->address ?? '',
            $candidate->city ?? '',
            $candidate->county ?? '',
            $candidate->postcode ?? '',
            $dayPeriod->date->format('d/m/Y'),
            $dayPeriod->period->label(),
            static::hoursFor($dayPeriod),
            $payRate,
            $payRate,
            $candidate->bank_account_name ?? '',
            $candidate->bank_account_number ?? '',
            $candidate->bank_sort_code ?? '',
        ];
    }

    private static function candidateName(?Model $candidate): string
    {
        if (! $candidate) {
            return 'Unknown candidate';
        }

        return trim("{$candidate->first_name} {$candidate->last_name}");
    }

    private static function hoursFor(BookingDay $dayPeriod): ?float
    {
        if (! $dayPeriod->time_from || ! $dayPeriod->time_to) {
            return null;
        }

        // time_from/time_to are plain TIME values with no date, so Carbon
        // anchors both to today. A shift that crosses midnight (the common
        // case for Waking Night, e.g. 22:00 -> 07:00) would otherwise diff
        // to its 24-hour complement — roll $to to the next day whenever
        // it's earlier than $from so the result reflects the actual elapsed
        // time.
        $from = Carbon::parse($dayPeriod->time_from);
        $to = Carbon::parse($dayPeriod->time_to);

        if ($to->lessThan($from)) {
            $to->addDay();
        }

        return round($from->diffInMinutes($to) / 60, 2);
    }
}
