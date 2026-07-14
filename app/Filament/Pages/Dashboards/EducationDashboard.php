<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Widgets\BookingsPerWeekChart;
use App\Filament\Widgets\EducationStatsOverview;

class EducationDashboard implements DashboardInterface
{
    public function getWidgets(): array
    {
        return [
            EducationStatsOverview::class,
            BookingsPerWeekChart::class,
        ];
    }

    public function getTitle(): string
    {
        return 'Home';
    }
}
