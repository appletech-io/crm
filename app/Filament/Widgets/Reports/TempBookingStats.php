<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\ReadsReportFilters;
use App\Services\Reporting\BookingRevenuePeriodCalculator;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TempBookingStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ReadsReportFilters;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $totals = BookingRevenuePeriodCalculator::totals(
            $this->periodStart(),
            $this->periodEnd(),
            $this->filterConsultantId(),
            $this->filterClientId(),
        );

        return [
            Stat::make('Bookings', $totals['bookings']),
            Stat::make('Revenue', '£'.number_format($totals['revenue'], 2)),
            Stat::make('Margin', '£'.number_format($totals['margin'], 2)),
            Stat::make('Avg Margin %', number_format($totals['avgMargin'] * 100, 1).'%'),
        ];
    }
}
