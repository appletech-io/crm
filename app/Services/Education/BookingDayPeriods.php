<?php

namespace App\Services\Education;

use App\Enums\BookingDayPeriod;
use App\Models\EducationBooking;
use App\Models\EducationBookingDayPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BookingDayPeriods
{
    /** @return Collection<int, array{date: Carbon, period: BookingDayPeriod, start: string, rate: ?float, hours: ?float}> */
    public static function rows(EducationBooking $booking, string $rateType = 'pay'): Collection
    {
        $rates = self::rates($booking, $rateType);

        return $booking->dayPeriods->map(fn (EducationBookingDayPeriod $dayPeriod): array => [
            'date' => $dayPeriod->date,
            'period' => $dayPeriod->period,
            'start' => self::formatTimes($dayPeriod),
            'rate' => $rates[$dayPeriod->period->value] ?? null,
            'hours' => self::totalHours($dayPeriod),
        ]);
    }

    private static function totalHours(EducationBookingDayPeriod $dayPeriod): ?float
    {
        if ($dayPeriod->period !== BookingDayPeriod::Hours || ! $dayPeriod->time_from || ! $dayPeriod->time_to) {
            return null;
        }

        return round(abs(Carbon::parse($dayPeriod->time_from)->diffInMinutes(Carbon::parse($dayPeriod->time_to))) / 60, 2);
    }

    private static function formatTimes(EducationBookingDayPeriod $dayPeriod): string
    {
        if (! $dayPeriod->time_from) {
            return '';
        }

        $from = Carbon::parse($dayPeriod->time_from)->format('H:i');

        if ($dayPeriod->period === BookingDayPeriod::Hours && $dayPeriod->time_to) {
            return $from.' - '.Carbon::parse($dayPeriod->time_to)->format('H:i');
        }

        return $from;
    }

    /** @return array<string, ?float> */
    private static function rates(EducationBooking $booking, string $rateType): array
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
