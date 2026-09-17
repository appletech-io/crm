<?php

namespace App\Filament\Pages\Dashboards;

use App\Filament\Widgets\GenericConsultantKpiOverview;
use App\Filament\Widgets\ItPlacementsOverview;

/**
 * The "Perm" dashboard — used for any industry that has Bookings toggled
 * off (see active_industry_uses_bookings()), and also directly selectable
 * via the Dashboard's header toggle when a sector has both Bookings and
 * Perm switched on (see Dashboard::resolveForBothEnabled()). Every widget
 * here is Vacancy/Application/Placement-derived instead of the
 * booking-based figures (Gross Profit, candidate days out, rebook rate,
 * booking-day leaderboard) a bookings-enabled industry's dashboard shows.
 */
class NoBookingsDashboard implements DashboardInterface
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
