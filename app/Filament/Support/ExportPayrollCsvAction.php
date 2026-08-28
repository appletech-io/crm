<?php

namespace App\Filament\Support;

use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\PaymentProvider;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the "Export CSV" header action on the Run Payroll page — the way a
 * company can get timesheet data out to run payroll manually, whether or
 * not they have a payroll provider (e.g. Evertime) configured. Column shape
 * is loosely modelled on a real Evertime bulk-upload template, cut down to
 * only what this app actually holds data for — Evertime-account-specific
 * plumbing (PO numbers, invoice point/rate-type/factor IDs, consultant
 * split percentages) has no equivalent here and isn't included.
 */
class ExportPayrollCsvAction
{
    private const array HEADINGS = [
        'Candidate', 'First Name', 'Last Name', 'Date of Birth', 'Gender', 'NI Number',
        'Email', 'Mobile', 'Home Phone', 'Address', 'City', 'County', 'Postcode',
        'Client', 'Client Phone', 'Client Contact', 'Client Contact Email',
        'Job Title', 'Consultant', 'Date', 'Session', 'Hours', 'Pay Rate', 'Charge Rate', 'Status',
        'Payment Method', 'Umbrella Company', 'Umbrella Address', 'Umbrella County', 'Umbrella Postcode',
        'Umbrella Reg Number', 'Umbrella VAT Number', 'Umbrella Email',
        'Bank Account Name', 'Bank Account Number', 'Sort Code',
    ];

    /**
     * @param  Closure(): Collection<int, BookingDay>  $dayPeriods
     * @param  Closure(): array{start: Carbon, end: Carbon}  $period
     */
    public static function header(Closure $dayPeriods, Closure $period): Action
    {
        return Action::make('exportPayrollCsv')
            ->label('Export CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (): StreamedResponse => static::download($dayPeriods(), $period()));
    }

    /**
     * Public so it's directly testable without going through a Filament
     * Action/Livewire context — the "Export CSV" button itself is just
     * header()'s thin wrapper around this.
     *
     * @param  Collection<int, BookingDay>  $dayPeriods
     * @param  array{start: Carbon, end: Carbon}  $period
     */
    public static function download(Collection $dayPeriods, array $period): StreamedResponse
    {
        $filename = 'payroll-'.$period['start']->toDateString().'-to-'.$period['end']->toDateString().'.csv';

        return response()->streamDownload(function () use ($dayPeriods): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, self::HEADINGS);

            foreach ($dayPeriods as $dayPeriod) {
                fputcsv($handle, static::row($dayPeriod));
            }

            fclose($handle);
        }, $filename);
    }

    /** @return array<int, mixed> */
    private static function row(BookingDay $dayPeriod): array
    {
        $booking = $dayPeriod->booking;
        $candidate = $booking?->candidate;
        $payee = static::payeeDetails($candidate);
        $clientContact = static::clientContact($booking?->client);

        return [
            static::candidateName($candidate),
            $candidate->first_name ?? '',
            $candidate->last_name ?? '',
            $candidate->date_of_birth?->toDateString() ?? '',
            $candidate->gender ?? '',
            $candidate->ni_number ?? '',
            $candidate->email ?? '',
            $candidate->mobile ?? '',
            $candidate->phone ?? '',
            $candidate->address ?? '',
            $candidate->city ?? '',
            $candidate->county ?? '',
            $candidate->postcode ?? '',
            static::clientName($booking),
            $booking?->client?->phone ?? '',
            $clientContact['name'],
            $clientContact['email'],
            $booking?->jobTitle?->name ?? '',
            $booking?->consultant?->name ?? '',
            $dayPeriod->date->format('d/m/Y'),
            $dayPeriod->period->label(),
            static::hoursWorked($dayPeriod),
            $dayPeriod->isCancelled() ? null : $dayPeriod->payRate(),
            $dayPeriod->isCancelled() ? null : $dayPeriod->chargeRate(),
            $dayPeriod->payrollStatus(),
            $payee['payment_method'],
            $payee['umbrella_company'],
            $payee['umbrella_address'],
            $payee['umbrella_county'],
            $payee['umbrella_postcode'],
            $payee['umbrella_reg_number'],
            $payee['umbrella_vat_number'],
            $payee['umbrella_email'],
            $payee['bank_account_name'],
            $payee['bank_account_number'],
            $payee['bank_sort_code'],
        ];
    }

    private static function candidateName(?Model $candidate): string
    {
        if (! $candidate) {
            return 'Unknown candidate';
        }

        $name = trim("{$candidate->first_name} {$candidate->last_name}");

        return $candidate->trashed() ? "{$name} (deleted)" : $name;
    }

    private static function clientName(?Booking $booking): string
    {
        $client = $booking?->client;

        if (! $client) {
            return 'Unknown client';
        }

        return $client->trashed() ? "{$client->name} (deleted)" : $client->name;
    }

    /** @return array{name: string, email: string} */
    private static function clientContact(?Client $client): array
    {
        $contact = $client?->bookingContact();

        if (! $contact) {
            return ['name' => '', 'email' => ''];
        }

        return [
            'name' => trim("{$contact->first_name} {$contact->last_name}"),
            'email' => $contact->email ?? '',
        ];
    }

    private static function hoursWorked(BookingDay $dayPeriod): ?float
    {
        if (! $dayPeriod->time_from || ! $dayPeriod->time_to) {
            return null;
        }

        return round(abs(Carbon::parse($dayPeriod->time_from)->diffInMinutes(Carbon::parse($dayPeriod->time_to))) / 60, 2);
    }

    /**
     * Who actually gets paid, and where — an umbrella/Ltd candidate is paid
     * via their Company's own bank details and profile, not their personal
     * account. Duck-typed via method_exists() rather than EducationCandidate|
     * HealthcareCandidate, since the generic Candidate has neither
     * payment_provider_id nor a paymentProvider() relation at all.
     *
     * @return array{
     *     payment_method: string, umbrella_company: string, umbrella_address: string,
     *     umbrella_county: string, umbrella_postcode: string, umbrella_reg_number: string,
     *     umbrella_vat_number: string, umbrella_email: string,
     *     bank_account_name: string, bank_account_number: string, bank_sort_code: string,
     * }
     */
    private static function payeeDetails(?Model $candidate): array
    {
        $blank = [
            'payment_method' => '', 'umbrella_company' => '', 'umbrella_address' => '',
            'umbrella_county' => '', 'umbrella_postcode' => '', 'umbrella_reg_number' => '',
            'umbrella_vat_number' => '', 'umbrella_email' => '',
            'bank_account_name' => '', 'bank_account_number' => '', 'bank_sort_code' => '',
        ];

        if (! $candidate) {
            return $blank;
        }

        $paymentProvider = ($candidate->payment_provider_id ?? null) && method_exists($candidate, 'paymentProvider')
            ? $candidate->paymentProvider
            : null;

        if ($paymentProvider instanceof PaymentProvider) {
            return [
                'payment_method' => 'Umbrella',
                'umbrella_company' => $paymentProvider->name,
                'umbrella_address' => trim(collect([$paymentProvider->address_1, $paymentProvider->address_2])->filter()->implode(', ')),
                'umbrella_county' => $paymentProvider->county ?? '',
                'umbrella_postcode' => $paymentProvider->postcode ?? '',
                'umbrella_reg_number' => $paymentProvider->company_reg_number ?? '',
                'umbrella_vat_number' => $paymentProvider->vat_reg_number ?? '',
                'umbrella_email' => $paymentProvider->email ?? '',
                'bank_account_name' => $paymentProvider->bank_account_name ?? '',
                'bank_account_number' => $paymentProvider->bank_account_number ?? '',
                'bank_sort_code' => $paymentProvider->bank_sort_code ?? '',
            ];
        }

        return [
            ...$blank,
            'payment_method' => $candidate->payment_method?->label() ?? '',
            'bank_account_number' => $candidate->bank_account_number ?? '',
            'bank_sort_code' => $candidate->bank_sort_code ?? '',
        ];
    }
}
