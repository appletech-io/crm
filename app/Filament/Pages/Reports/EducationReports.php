<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\Reports\BookingRevenueChart;
use App\Filament\Widgets\Reports\JobPipelineChart;
use App\Filament\Widgets\Reports\PermPlacementStats;
use App\Filament\Widgets\Reports\PlacementsChart;
use App\Filament\Widgets\Reports\TempBookingStats;
use App\Filament\Widgets\Reports\TopClientsTable;

/**
 * Education and Healthcare report identically today — both sectors book
 * temp work and place permanent roles the same way at the data level. This
 * class split exists so a sector-specific breakdown (e.g. a healthcare shift
 * type or an education key stage view) has an obvious place to go later,
 * matching how Dashboards/EducationDashboard and HealthcareDashboard split.
 */
class EducationReports implements ReportsInterface
{
    public function getWidgets(): array
    {
        return [
            TempBookingStats::class,
            PermPlacementStats::class,
            BookingRevenueChart::class,
            PlacementsChart::class,
            JobPipelineChart::class,
            TopClientsTable::class,
        ];
    }

    public function getTitle(): string
    {
        return 'Reports';
    }

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array
    {
        return 2;
    }
}
