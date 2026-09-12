<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Widgets\GenericConsultantKpiOverview;
use App\Filament\Widgets\ItPlacementsOverview;

/**
 * No Bookings exist for this industry — every widget here is Vacancy/
 * Application/Placement-derived instead of the booking-based figures
 * (Gross Profit, candidate days out, rebook rate, booking-day leaderboard)
 * the Education/Healthcare/Construction dashboards show.
 */
class ItDashboard implements DashboardInterface
{
    /** @return array<int, class-string> */
    public function getWidgets(): array
    {
        return [
            ItPlacementsOverview::class,
            GenericConsultantKpiOverview::class,
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
