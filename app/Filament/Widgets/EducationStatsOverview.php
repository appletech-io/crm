<?php

namespace App\Filament\Widgets;

use App\Models\EducationBooking;
use App\Models\EducationCandidate;
use App\Models\EducationClient;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EducationStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Clients', EducationClient::count())
                ->description('Total education clients')
                ->descriptionIcon('heroicon-m-user-group'),
            Stat::make('Total Candidates', EducationCandidate::count())
                ->description('Total education candidates')
                ->descriptionIcon('heroicon-m-users'),
            Stat::make('Total Bookings', EducationBooking::count())
                ->description('Total education bookings')
                ->descriptionIcon('heroicon-m-calendar'),
        ];
    }
}
