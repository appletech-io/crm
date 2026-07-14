<?php

namespace App\Enums;

enum BookingDayPeriod: string
{
    case Am = 'am';
    case Pm = 'pm';
    case FullDay = 'full_day';
    case Hours = 'hours';

    public function label(): string
    {
        return match ($this) {
            self::Am => 'AM',
            self::Pm => 'PM',
            self::FullDay => 'Full Day',
            self::Hours => 'Hours',
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
