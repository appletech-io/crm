<?php

namespace App\Enums;

enum ActionEmailRecipient: string
{
    case Candidate = 'candidate';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Candidate => 'The booking\'s candidate',
            self::Client => 'The booking\'s client',
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
