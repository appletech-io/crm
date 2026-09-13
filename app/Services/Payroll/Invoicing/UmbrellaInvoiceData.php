<?php

namespace App\Services\Payroll\Invoicing;

use App\Enums\PaymentMethod;
use App\Models\BookingDay;
use App\Models\Company;
use App\Models\PaymentProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shapes a company's approved booking days for a payroll period into one
 * group per umbrella company (PaymentProvider), each with one invoice line
 * per candidate — this is a self-bill (the agency produces the invoice on
 * the umbrella's behalf, since the agency holds the timesheet data), so
 * amounts use payRate() — what the agency remits to the umbrella — rather
 * than the client charge rate. Only candidates with payment_method Umbrella
 * are included; a candidate with no PaymentProvider set (data inconsistency
 * between payment_method and payment_provider_id) is skipped rather than
 * guessed at.
 */
class UmbrellaInvoiceData
{
    /**
     * @return Collection<int, array{provider: PaymentProvider, lines: array<int, array{
     *     description: string, quantity: float, unit_rate: float, amount: float,
     *     candidate_type: ?string, candidate_id: ?int,
     * }>}>
     */
    public static function forPeriod(Company $company, Carbon $start, Carbon $end): Collection
    {
        return static::approvedUmbrellaDays($company, $start, $end)
            ->groupBy(fn (BookingDay $day) => $day->booking->candidate->payment_provider_id)
            ->map(fn (Collection $providerDays): array => [
                'provider' => $providerDays->first()->booking->candidate->paymentProvider,
                'lines' => static::linesFor($providerDays),
            ])
            ->filter(fn (array $group): bool => $group['provider'] instanceof PaymentProvider)
            ->values();
    }

    /** @return Collection<int, BookingDay> */
    private static function approvedUmbrellaDays(Company $company, Carbon $start, Carbon $end): Collection
    {
        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->where('company_id', $company->id)->excludingRequests())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNull('cancelled_at')
            ->whereNotNull('approved_at')
            ->with(['booking.client', 'booking.candidate.paymentProvider', 'booking.jobTitle'])
            ->get()
            ->filter(function (BookingDay $day): bool {
                $candidate = $day->booking->candidate;

                return $candidate
                    && method_exists($candidate, 'paymentProvider')
                    && $candidate->payment_method === PaymentMethod::Umbrella
                    && $candidate->payment_provider_id;
            });
    }

    /**
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
                $client = $first->booking->client;
                $jobTitle = $first->booking->jobTitle?->name;

                $quantity = $candidateDays->count();
                $amount = $candidateDays->sum(fn (BookingDay $day): float => $day->payRate() ?? 0);

                $label = trim("{$candidate->first_name} {$candidate->last_name}");
                $context = collect([$jobTitle, $client?->name])->filter()->implode(' — ');

                return [
                    'description' => $context ? "{$label} ({$context})" : $label,
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
}
