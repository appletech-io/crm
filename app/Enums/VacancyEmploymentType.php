<?php

namespace App\Enums;

enum VacancyEmploymentType: string
{
    case Permanent = 'permanent';
    case Temp = 'temp';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::Temp => 'Temp',
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
