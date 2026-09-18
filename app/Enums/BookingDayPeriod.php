<?php

namespace App\Enums;

enum BookingDayPeriod: string
{
    case Am = 'am';
    case Pm = 'pm';
    case FullDay = 'full_day';
    case Hours = 'hours';
    case SleepIn = 'sleep_in';
    case WakingNight = 'waking_night';

    public function label(): string
    {
        return match ($this) {
            self::Am => 'AM',
            self::Pm => 'PM',
            self::FullDay => 'Full Day',
            self::Hours => 'Hours',
            self::SleepIn => 'Sleep-In',
            self::WakingNight => 'Waking Night',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
