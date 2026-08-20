<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\ReadsReportFilters;
use App\Services\Reporting\PlacementPeriodCalculator;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PermPlacementStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ReadsReportFilters;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $totals = PlacementPeriodCalculator::totals(
            $this->periodStart(),
            $this->periodEnd(),
            $this->filterConsultantId(),
            $this->filterClientId(),
        );

        return [
            Stat::make('Placements', $totals['count']),
            Stat::make('Value', '£'.number_format($totals['value'], 2)),
            Stat::make('Avg Value', '£'.number_format($totals['avgValue'], 2)),
        ];
    }
}
