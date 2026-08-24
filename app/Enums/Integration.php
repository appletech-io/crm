<?php

namespace App\Enums;

enum Integration: string
{
    case Evertime = 'evertime';

    public function label(): string
    {
        return match ($this) {
            self::Evertime => 'Evertime',
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
