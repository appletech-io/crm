<?php

namespace App\Enums\Education;

enum Availability: string
{
    case DayToDay = 'day_to_day';
    case LongTerm = 'long_term';
    case ShortTerm = 'short_term';
    case PartTime = 'part_time';
    case Permanent = 'permanent';
    case HasTransport = 'has_transport';

    public function label(): string
    {
        return match ($this) {
            self::DayToDay => 'Day to Day',
            self::LongTerm => 'Long Term',
            self::ShortTerm => 'Short Term',
            self::PartTime => 'Part Time',
            self::Permanent => 'Permanent',
            self::HasTransport => 'Has Transport',
        };
    }
}
