<?php

namespace App\Services\Payroll\Invoicing;

use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shapes a company's approved booking days for a payroll period into one
 * group per client, each with one invoice line per candidate — kept apart
 * from the PDF/persistence mechanics in GenerateClientInvoices so this
 * grouping/rate logic is directly testable, mirroring ClientTimesheetData.
 */
class ClientInvoiceData
{
    /**
     * @return Collection<int, array{client: Client, lines: array<int, array{
     *     description: string, quantity: float, unit_rate: float, amount: float,
     *     candidate_type: ?string, candidate_id: ?int,
     * }>}>
     */
    public static function forPeriod(Company $company, Carbon $start, Carbon $end): Collection
    {
        return static::approvedDays($company, $start, $end)
            ->groupBy(fn (BookingDay $day): int => $day->booking->client_id)
            ->map(fn (Collection $clientDays): array => [
                'client' => $clientDays->first()->booking->client,
                'lines' => static::linesFor($clientDays),
            ])
            ->filter(fn (array $group): bool => $group['client'] !== null)
            ->values();
    }

    /** @return Collection<int, BookingDay> */
    private static function approvedDays(Company $company, Carbon $start, Carbon $end): Collection
    {
        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->where('company_id', $company->id)->excludingRequests())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNull('cancelled_at')
            ->whereNotNull('approved_at')
            ->with(['booking.client', 'booking.candidate', 'booking.jobTitle'])
            ->get();
    }

    /**
     * One line per candidate booked at this client during the period — days
     * are summed rather than listed individually, since a week can mix
     * full-day/half-day/hourly rates for the same candidate; unit_rate shown
     * is the resulting blended rate, not a promise every day matched it.
     *
     * @param  Collection<int, BookingDay>  $days
     * @return array<int, array{description: string, quantity: float, unit_rate: float, amount: float, candidate_type: ?string, candidate_id: ?int}>
     */
    private static function linesFor(Collection $days): array
    {
        return $days
            ->groupBy(fn (BookingDay $day): string => "{$day->booking->candidate_type}|{$day->booking->candidate_id}")
            ->map(function (Collection $candidateDays): array {
                $first = $candidateDays->first();
                $candidate = $first->booking->candidate;
                $jobTitle = $first->booking->jobTitle?->name;

                $quantity = $candidateDays->count();
                $amount = $candidateDays->sum(fn (BookingDay $day): float => $day->chargeRate() ?? 0);

                return [
                    'description' => trim(static::candidateName($candidate).($jobTitle ? " — {$jobTitle}" : '')),
                    'quantity' => $quantity,
                    'unit_rate' => $quantity > 0 ? round($amount / $quantity, 2) : 0.0,
                    'amount' => round($amount, 2),
                    'candidate_type' => $first->booking->candidate_type,
                    'candidate_id' => $first->booking->candidate_id,
                ];
            })
            ->values()
            ->all();
    }

    private static function candidateName(mixed $candidate): string
    {
        if (! $candidate) {
            return 'Unknown candidate';
        }

        return trim("{$candidate->first_name} {$candidate->last_name}");
    }
}
