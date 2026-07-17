<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Widgets\BookingsPerDayChart;
use App\Filament\Widgets\EducationStatsOverview;

class EducationDashboard implements DashboardInterface
{
    public function getWidgets(): array
    {
        return [
            EducationStatsOverview::class,
            BookingsPerDayChart::class,
        ];
    }

    public function getTitle(): string
    {
        return 'Home';
    }
}
