<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Widgets\EducationConsultantKpiOverview;
use App\Filament\Widgets\EducationConsultantLeaderboard;

class EducationDashboard implements DashboardInterface
{
    public function getWidgets(): array
    {
        return [
            EducationConsultantKpiOverview::class,
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
