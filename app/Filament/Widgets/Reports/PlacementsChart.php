<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\ReadsReportFilters;
use App\Services\Reporting\PlacementPeriodCalculator;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PlacementsChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ReadsReportFilters;

    protected ?string $heading = 'Permanent Placements';

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $weeks = PlacementPeriodCalculator::byWeek(
            $this->periodStart(),
            $this->periodEnd(),
            $this->filterConsultantId(),
            $this->filterClientId(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Placements',
                    'data' => $weeks->pluck('count')->all(),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $weeks->map(fn (array $week): string => $week['weekStart']->format('d M'))->all(),
        ];
    }
}
