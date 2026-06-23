<?php

namespace App\Enums\Education;

enum KeyStage: string
{
    case Nursery = 'nursery';
    case KeyStage1 = 'keystage_1';
    case KeyStage2 = 'keystage_2';
    case KeyStage3 = 'keystage_3';
    case KeyStage4 = 'keystage_4';
    case KeyStage5 = 'keystage_5';
    case SEN = 'sen';

    public function label(): string
    {
        return match ($this) {
            self::Nursery => 'Nursery',
            self::KeyStage1 => 'Keystage 1',
            self::KeyStage2 => 'Keystage 2',
            self::KeyStage3 => 'Keystage 3',
            self::KeyStage4 => 'Keystage 4',
            self::KeyStage5 => 'Keystage 5',
            self::SEN => 'SEN',
        };
    }
}
