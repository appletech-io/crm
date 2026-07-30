<?php

namespace App\Enums;

enum EmailTemplateAudience: string
{
    case Candidate = 'candidate';
    case Client = 'client';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Candidate => 'Candidates',
            self::Client => 'Clients',
            self::Both => 'Candidates & Clients',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
