<?php

namespace App\Enums;

enum ReferenceType: string
{
    case Professional = 'professional';
    case Character = 'character';
    case Academic = 'academic';
    case Agency = 'agency';
    case GapStatement = 'gap_statement';

    public function label(): string
    {
        return match ($this) {
            self::Professional => 'Professional',
            self::Character => 'Character',
            self::Academic => 'Academic',
            self::Agency => 'Agency',
            self::GapStatement => 'Gap / Statement',
        };
    }
}
