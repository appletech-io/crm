<?php

namespace App\Enums\Education;

enum EmploymentType: string
{
    case DailySupply = 'daily_supply';
    case ShortTerm = 'short_term';
    case LongTerm = 'long_term';
    case Permanent = 'permanent';

    public function label(): string
    {
        return match ($this) {
            self::DailySupply => 'Daily Supply',
            self::ShortTerm => 'Short Term',
            self::LongTerm => 'Long Term',
            self::Permanent => 'Permanent',
        };
    }
}
