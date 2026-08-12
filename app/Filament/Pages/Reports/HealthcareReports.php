<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\Reports\BookingRevenueChart;
use App\Filament\Widgets\Reports\JobPipelineChart;
use App\Filament\Widgets\Reports\PermPlacementStats;
use App\Filament\Widgets\Reports\PlacementsChart;
use App\Filament\Widgets\Reports\TempBookingStats;
use App\Filament\Widgets\Reports\TopClientsTable;

/** @see EducationReports for why this currently mirrors it exactly. */
class HealthcareReports implements ReportsInterface
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
