<?php

namespace App\Services\Booking;

use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\BookingDay;
use Illuminate\Support\Collection;

/**
 * The single source of truth for pay/charge/on-cost/margin arithmetic.
 * Used by the booking form's live "Margin Calculator" section and by the
 * revenue & margin reporting pages, so both agree on the same figures
 * instead of the reports maintaining their own, separate approximation.
 */
class MarginCalculator
{
    /**
     * Applied to the pay cost only for a PAYE candidate — this agency's
     * standard approximation of employer's National Insurance and other
     * statutory on-costs. An umbrella company candidate is invoiced as a
     * single fee that already covers their own employment costs, so there's
     * no additional on-cost to the agency on top of the pay rate for them.
     */
    public const PAYE_ONCOST_RATE = 0.15;

    public static function oncostRate(?PaymentMethod $paymentMethod): float
    {
        return $paymentMethod === PaymentMethod::Paye ? self::PAYE_ONCOST_RATE : 0.0;
    }

    /**
     * @return array{
     *     paymentMethod: ?PaymentMethod,
     *     totalPay: float,
     *     totalCharge: float,
     *     oncosts: float,
     *     margin: float,
     *     marginPercent: float,
     * }
     */
    public static function breakdown(float $totalPay, float $totalCharge, ?PaymentMethod $paymentMethod): array
    {
        $oncosts = round($totalPay * self::oncostRate($paymentMethod), 2);
        $margin = round($totalCharge - $totalPay - $oncosts, 2);

        return [
            'paymentMethod' => $paymentMethod,
            'totalPay' => round($totalPay, 2),
            'totalCharge' => round($totalCharge, 2),
            'oncosts' => $oncosts,
            'margin' => $margin,
            'marginPercent' => $totalCharge > 0 ? round(($margin / $totalCharge) * 100, 1) : 0.0,
        ];
    }

    /**
     * Pay/charge for each of a booking's non-cancelled day periods, with an
     * Hours-period day scaled by the hours actually scheduled — the same
     * per-day unit handling as the booking form's live calculator.
     *
     * @param  Collection<int, BookingDay>  $dayPeriods
     * @return Collection<int, array{pay: float, charge: float}>
     */
    public static function dayAmounts(Collection $dayPeriods): Collection
    {
        return $dayPeriods
            ->reject(fn (BookingDay $day): bool => $day->isCancelled())
            ->map(fn (BookingDay $day): array => [
                'pay' => ($day->payRate() ?? 0) * BookingDayPeriods::unitsFor($day),
                'charge' => ($day->chargeRate() ?? 0) * BookingDayPeriods::unitsFor($day),
            ])
            ->values();
    }

    /**
     * The full breakdown for an already-persisted booking, from its actual
     * scheduled days rather than unsaved form state.
     *
     * @param  ?Collection<int, BookingDay>  $dayPeriods  A pre-filtered/loaded subset (e.g. a date range) to use instead of the booking's full day history.
     */
    public static function forBooking(Booking $booking, ?Collection $dayPeriods = null): array
    {
        $amounts = self::dayAmounts($dayPeriods ?? $booking->dayPeriods);

        return self::breakdown(
            $amounts->sum('pay'),
            $amounts->sum('charge'),
            $booking->candidate?->payment_method,
        );
    }
}
