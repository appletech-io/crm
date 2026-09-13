<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\Reports\JobPipelineChart;
use App\Filament\Widgets\Reports\PermPlacementStats;
use App\Filament\Widgets\Reports\PlacementsChart;
use App\Filament\Widgets\Reports\TopClientsByPlacementsTable;

/**
 * No Bookings exist for this industry, so every booking-revenue widget
 * (TempBookingStats, BookingRevenueChart, TopClientsTable) is dropped
 * entirely rather than shown at a meaningless zero — only the
 * permanent-placement figures apply.
 */
class ItReports implements ReportsInterface
{
    public function getWidgets(): array
    {
        return [
            PermPlacementStats::class,
            PlacementsChart::class,
            JobPipelineChart::class,
            TopClientsByPlacementsTable::class,
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
