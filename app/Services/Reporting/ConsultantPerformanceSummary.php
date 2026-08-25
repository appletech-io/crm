<?php

namespace App\Services\Reporting;

use App\Filament\Resources\Bookings\Widgets\BookingWeekStats;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Services\Booking\BookingDayPeriods;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The same gross profit / days-placed / clients / candidates math as
 * {@see BookingWeekStats}, but for
 * an arbitrary week and consultant rather than hardcoded to "now" and the
 * current user — so it can be reused for both this week's figures and next
 * week's (for the rebook rate) without duplicating the query.
 */
class ConsultantPerformanceSummary
{
    /** @return array{clients: int, candidates: int, gp: float, avgMargin: float, daysPlaced: int} */
    public static function forWeek(?int $consultantId, CarbonInterface $weekStart): array
    {
        $start = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        return self::forRange($consultantId, $start, $end);
    }

    /**
     * Same figures as {@see self::forWeek()}, but for an arbitrary date
     * range rather than one that's snapped to week boundaries — used for
     * "last 1/3 months" period totals on the monthly report.
     *
     * @return array{clients: int, candidates: int, gp: float, avgMargin: float, daysPlaced: int}
     */
    public static function forRange(?int $consultantId, CarbonInterface $start, CarbonInterface $end): array
    {
        $dayPeriods = BookingDay::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNull('cancelled_at')
            ->whereHas('booking', function ($query) use ($consultantId): void {
                $query->forActiveIndustry();

                if ($consultantId) {
                    $query->where('consultant_id', $consultantId);
                }
            })
            ->with('booking')
            ->get();

        $bookings = $dayPeriods->pluck('booking')->unique('id');

        $totalCharge = 0.0;
        $gp = $dayPeriods->groupBy('booking_id')->sum(function ($periods) use (&$totalCharge) {
            /** @var Booking $booking */
            $booking = $periods->first()->booking;
            $payRates = BookingDayPeriods::ratesFor($booking, 'pay');
            $chargeRates = BookingDayPeriods::ratesFor($booking, 'charge');

            $totalCharge += $periods->sum(
                fn (BookingDay $period): float => $chargeRates[$period->period->value] ?? 0
            );

            return $periods->sum(
                fn (BookingDay $period): float => ($chargeRates[$period->period->value] ?? 0) - ($payRates[$period->period->value] ?? 0)
            );
        });

        return [
            'clients' => $bookings->pluck('client_id')->unique()->count(),
            'candidates' => $bookings->map(fn (Booking $booking): string => "{$booking->candidate_type}|{$booking->candidate_id}")->unique()->count(),
            'gp' => round($gp, 2),
            'avgMargin' => $totalCharge > 0 ? round($gp / $totalCharge, 4) : 0.0,
            'daysPlaced' => $dayPeriods->count(),
        ];
    }

    /**
     * Next week's booked days as a percentage of this week's — e.g. 40 days
     * booked this week and 32 already booked for next week is a 80% rebook
     * rate. Null when there were no days this week to compare against.
     */
    public static function rebookRate(?int $consultantId, CarbonInterface $weekStart): ?float
    {
        $thisWeekDays = self::forWeek($consultantId, $weekStart)['daysPlaced'];

        if ($thisWeekDays === 0) {
            return null;
        }

        $nextWeekDays = self::forWeek($consultantId, Carbon::parse($weekStart)->addWeek())['daysPlaced'];

        return round(($nextWeekDays / $thisWeekDays) * 100, 1);
    }

    /**
     * forWeek()'s figures for every week (Monday to Sunday) touching the
     * given range, oldest first — the week-by-week table on the monthly
     * report.
     *
     * @return Collection<int, array{weekStart: string, clients: int, candidates: int, gp: float, avgMargin: float, daysPlaced: int}>
     */
    public static function weeklyBreakdown(?int $consultantId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $weekStart = Carbon::parse($start)->startOfWeek(Carbon::MONDAY);
        $lastWeekStart = Carbon::parse($end)->startOfWeek(Carbon::MONDAY);

        $weeks = new Collection;

        while ($weekStart->lte($lastWeekStart)) {
            $weeks->push(array_merge(
                ['weekStart' => $weekStart->toDateString()],
                self::forWeek($consultantId, $weekStart),
            ));

            $weekStart = $weekStart->copy()->addWeek();
        }

        return $weeks;
    }
}
