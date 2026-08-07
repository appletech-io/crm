<?php

namespace App\Services\Booking;

use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\BookingDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BookingDayPeriods
{
    private const DEFAULT_FULL_DAY_START = '08:30';

    /** @return Collection<int, array{date: Carbon, period: BookingDayPeriod, start: string, rate: ?float, hours: ?float, cancelled: bool}> */
    public static function rows(Booking $booking, string $rateType = 'pay'): Collection
    {
        $rates = self::ratesFor($booking, $rateType);

        return $booking->dayPeriods->map(fn (BookingDay $dayPeriod): array => [
            'date' => $dayPeriod->date,
            'period' => $dayPeriod->period,
            'start' => self::formatTimes($dayPeriod),
            'rate' => $dayPeriod->isCancelled() ? null : ($rates[$dayPeriod->period->value] ?? null),
            'hours' => self::totalHours($dayPeriod),
            'cancelled' => $dayPeriod->isCancelled(),
        ]);
    }

    private static function totalHours(BookingDay $dayPeriod): ?float
    {
        if ($dayPeriod->period !== BookingDayPeriod::Hours || ! $dayPeriod->time_from || ! $dayPeriod->time_to) {
            return null;
        }

        return round(abs(Carbon::parse($dayPeriod->time_from)->diffInMinutes(Carbon::parse($dayPeriod->time_to))) / 60, 2);
    }

    private static function formatTimes(BookingDay $dayPeriod): string
    {
        if (! $dayPeriod->time_from) {
            // A full day booking has no specific start time recorded, but the
            // confirmation PDF should still show a sensible default rather
            // than a blank cell.
            return $dayPeriod->period === BookingDayPeriod::FullDay
                ? self::DEFAULT_FULL_DAY_START
                : '';
        }

        $from = Carbon::parse($dayPeriod->time_from)->format('H:i');

        if ($dayPeriod->period === BookingDayPeriod::Hours && $dayPeriod->time_to) {
            return $from.' - '.Carbon::parse($dayPeriod->time_to)->format('H:i');
        }

        return $from;
    }

    /** @return array<string, ?float> */
    public static function ratesFor(Booking $booking, string $rateType): array
    {
        return $rateType === 'charge'
            ? [
                BookingDayPeriod::FullDay->value => $booking->day_charge_rate,
                BookingDayPeriod::Am->value => $booking->half_day_charge_rate,
                BookingDayPeriod::Pm->value => $booking->half_day_charge_rate,
                BookingDayPeriod::Hours->value => $booking->hourly_charge_rate,
            ]
            : [
                BookingDayPeriod::FullDay->value => $booking->day_rate,
                BookingDayPeriod::Am->value => $booking->half_day_rate,
                BookingDayPeriod::Pm->value => $booking->half_day_rate,
                BookingDayPeriod::Hours->value => $booking->hourly_rate,
            ];
    }
}
