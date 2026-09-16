<?php

namespace App\Services\Reporting;

use App\Filament\Resources\Bookings\Widgets\BookingWeekStats;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Services\Booking\MarginCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Generalizes the revenue/cost/margin math from
 * {@see BookingWeekStats::weekStats()}
 * — same grouping and rate lookups, but over an arbitrary date range and
 * optional consultant/client filters instead of a fixed current week.
 */
class BookingRevenuePeriodCalculator
{
    /** @return Collection<int, array{weekStart: Carbon, revenue: float, cost: float, margin: float, bookings: int}> */
    public static function byWeek(Carbon $start, Carbon $end, ?int $consultantId = null, ?int $clientId = null): Collection
    {
        return self::dayPeriodsQuery($start, $end, $consultantId, $clientId)
            ->get()
            ->groupBy(fn (BookingDay $day): string => $day->date->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(function (Collection $periods, string $weekStart): array {
                [$revenue, $cost] = self::sumRevenueAndCost($periods);

                return [
                    'weekStart' => Carbon::parse($weekStart),
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'margin' => round($revenue - $cost, 2),
                    'bookings' => $periods->pluck('booking_id')->unique()->count(),
                ];
            })
            ->sortKeys()
            ->values();
    }

    /**
     * Ranked by revenue, descending — used for a "top clients" listing
     * rather than a time series.
     *
     * @return Collection<int, array{clientId: int, clientName: string, revenue: float, cost: float, margin: float, bookings: int}>
     */
    public static function byClient(Carbon $start, Carbon $end, ?int $consultantId = null, ?int $clientId = null): Collection
    {
        return self::dayPeriodsQuery($start, $end, $consultantId, $clientId)
            ->get()
            ->groupBy(fn (BookingDay $day): int => $day->booking->client_id)
            ->map(function (Collection $periods, int $groupClientId): array {
                [$revenue, $cost] = self::sumRevenueAndCost($periods);

                /** @var Booking $booking */
                $booking = $periods->first()->booking;

                return [
                    'clientId' => $groupClientId,
                    'clientName' => $booking->client?->name ?? 'Unknown client',
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'margin' => round($revenue - $cost, 2),
                    'bookings' => $periods->pluck('booking_id')->unique()->count(),
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * Same grouping as {@see self::byClient()} but by booking, for a
     * row-level revenue/cost/margin report rather than a per-client rollup.
     *
     * @return Collection<int, array{bookingId: int, clientName: string, consultantName: string, jobTitle: string, revenue: float, cost: float, margin: float, days: int}>
     */
    public static function byBooking(Carbon $start, Carbon $end, ?int $consultantId = null, ?int $clientId = null): Collection
    {
        return self::dayPeriodsQuery($start, $end, $consultantId, $clientId)
            ->with('booking.consultant', 'booking.jobTitle')
            ->get()
            ->groupBy('booking_id')
            ->map(function (Collection $periods, int $bookingId): array {
                [$revenue, $cost] = self::sumRevenueAndCost($periods);

                /** @var Booking $booking */
                $booking = $periods->first()->booking;

                return [
                    'bookingId' => $bookingId,
                    'clientName' => $booking->client?->name ?? 'Unknown client',
                    'consultantName' => $booking->consultant?->name ?? 'Unassigned',
                    'jobTitle' => $booking->jobTitle?->name ?? 'Unknown role',
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'margin' => round($revenue - $cost, 2),
                    'days' => $periods->pluck('date')->unique()->count(),
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    /** @return array{bookings: int, revenue: float, cost: float, margin: float, avgMargin: float} */
    public static function totals(Carbon $start, Carbon $end, ?int $consultantId = null, ?int $clientId = null): array
    {
        $dayPeriods = self::dayPeriodsQuery($start, $end, $consultantId, $clientId)->get();

        [$revenue, $cost] = self::sumRevenueAndCost($dayPeriods);

        return [
            'bookings' => $dayPeriods->pluck('booking_id')->unique()->count(),
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'margin' => round($revenue - $cost, 2),
            'avgMargin' => $revenue > 0 ? round(($revenue - $cost) / $revenue, 4) : 0.0,
        ];
    }

    /**
     * If the last week in $weeks is still in progress as of $periodEnd,
     * scale its partial total up to a full week — lets the chart plot a
     * projected figure for the current week instead of an artificially low
     * dip caused by the period filter cutting the week off early.
     *
     * @param  Collection<int, array{weekStart: Carbon, revenue: float, cost: float, margin: float, bookings: int}>  $weeks
     * @return ?array{revenue: float, cost: float, margin: float}
     */
    public static function projectCurrentWeek(Collection $weeks, Carbon $periodEnd): ?array
    {
        if ($weeks->isEmpty()) {
            return null;
        }

        $lastWeek = $weeks->last();
        $weekEnd = $lastWeek['weekStart']->copy()->endOfWeek(Carbon::SUNDAY);

        if ($periodEnd->gte($weekEnd)) {
            return null;
        }

        $daysElapsed = min(7, max(1, $lastWeek['weekStart']->diffInDays($periodEnd) + 1));
        $scale = 7 / $daysElapsed;

        return [
            'revenue' => round($lastWeek['revenue'] * $scale, 2),
            'cost' => round($lastWeek['cost'] * $scale, 2),
            'margin' => round($lastWeek['margin'] * $scale, 2),
        ];
    }

    private static function dayPeriodsQuery(Carbon $start, Carbon $end, ?int $consultantId, ?int $clientId): Builder
    {
        return BookingDay::query()
            ->whereBetween('date', [$start->copy()->toDateString(), $end->copy()->toDateString()])
            ->whereNull('cancelled_at')
            ->whereHas('booking', function (Builder $query) use ($consultantId, $clientId): void {
                $query->forActiveIndustry();

                if ($consultantId) {
                    $query->where('consultant_id', $consultantId);
                }

                if ($clientId) {
                    $query->where('client_id', $clientId);
                }
            })
            ->with('booking.client', 'booking.candidate');
    }

    /**
     * Cost includes each booking's PAYE employer on-costs where applicable
     * (see {@see MarginCalculator}), on top of the raw pay rate, so the
     * margin this produces matches the booking form's own calculator.
     *
     * @param  Collection<int, BookingDay>  $periods
     * @return array{0: float, 1: float}
     */
    private static function sumRevenueAndCost(Collection $periods): array
    {
        $revenue = 0.0;
        $cost = 0.0;

        foreach ($periods->groupBy('booking_id') as $bookingPeriods) {
            /** @var Booking $booking */
            $booking = $bookingPeriods->first()->booking;
            $breakdown = MarginCalculator::forBooking($booking, $bookingPeriods);

            $revenue += $breakdown['totalCharge'];
            $cost += $breakdown['totalPay'] + $breakdown['oncosts'];
        }

        return [$revenue, $cost];
    }
}
