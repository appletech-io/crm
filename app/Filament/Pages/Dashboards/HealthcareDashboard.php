<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Widgets\ConsultantPerformanceSummary;
use App\Filament\Widgets\EducationConsultantLeaderboard;
use App\Filament\Widgets\HealthcareConsultantKpiOverview;

class HealthcareDashboard implements DashboardInterface
{
    public function getWidgets(): array
    {
        return [
            ConsultantPerformanceSummary::class,
            HealthcareConsultantKpiOverview::class,
            EducationConsultantLeaderboard::class,
        ];
    }

    public function getTitle(): string
    {
        return 'Home';
    }

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array
    {
        return 2;
    }
}
