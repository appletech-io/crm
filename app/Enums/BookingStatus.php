<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Upcoming = 'upcoming';
    case Approved = 'approved';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Approved => 'Approved',
            self::Completed => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Upcoming => 'gray',
            self::Approved => 'success',
            self::Completed => 'info',
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
