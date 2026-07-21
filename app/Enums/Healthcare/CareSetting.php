<?php

namespace App\Enums\Healthcare;

enum CareSetting: string
{
    case Hospital = 'hospital';
    case CareHome = 'care_home';
    case DomiciliaryCare = 'domiciliary_care';
    case GpSurgery = 'gp_surgery';
    case MentalHealth = 'mental_health';
    case Community = 'community';

    public function label(): string
    {
        return match ($this) {
            self::Hospital => 'Hospital',
            self::CareHome => 'Care Home',
            self::DomiciliaryCare => 'Domiciliary Care',
            self::GpSurgery => 'GP Surgery',
            self::MentalHealth => 'Mental Health',
            self::Community => 'Community',
        };
    }
}
