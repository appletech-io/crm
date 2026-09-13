<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Client = 'client';
    case Umbrella = 'umbrella';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client Invoice',
            self::Umbrella => 'Umbrella Self-Bill',
        };
    }
}
