<?php

namespace App\Enums\Healthcare;

enum Wellbeing: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::Poor => 'Poor',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Good => 'success',
            self::Fair => 'warning',
            self::Poor => 'danger',
        };
    }
}
