<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Paye = 'paye';
    case Umbrella = 'umbrella';

    public function label(): string
    {
        return match ($this) {
            self::Paye => 'PAYE',
            self::Umbrella => 'Umbrella / Ltd Company',
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
