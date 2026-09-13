<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Widgets\ConsultantPerformanceSummary;
use App\Filament\Widgets\EducationConsultantLeaderboard;
use App\Filament\Widgets\GenericConsultantKpiOverview;

class ConstructionDashboard implements DashboardInterface
{
    /** @return array<int, class-string> */
    public function getWidgets(): array
    {
        return [
            ConsultantPerformanceSummary::class,
            GenericConsultantKpiOverview::class,
            EducationConsultantLeaderboard::class,
        ];
    }

    public function getTitle(): string
    {
        return 'Home';
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
