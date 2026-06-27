<?php

namespace App\Enums;

enum EmailProvider: string
{
    case Microsoft = 'microsoft';
    case Mailgun = 'mailgun';

    public function label(): string
    {
        return match ($this) {
            self::Microsoft => 'Microsoft / Outlook',
            self::Mailgun => 'Mailgun',
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
