<?php

namespace App\Enums;

enum EmailTemplateType: string
{
    case General = 'general';
    case Application = 'application';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Application => 'Application Email',
        };
    }
}
