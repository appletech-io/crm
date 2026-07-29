<?php

namespace App\Enums;

enum ReferenceType: string
{
    case Professional = 'professional';
    case Character = 'character';
    case Academic = 'academic';
    case Agency = 'agency';

    public function label(): string
    {
        return match ($this) {
            self::Professional => 'Professional',
            self::Character => 'Character',
            self::Academic => 'Academic',
            self::Agency => 'Agency',
        };
    }
}
