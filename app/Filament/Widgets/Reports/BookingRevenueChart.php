<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\ReadsReportFilters;
use App\Services\Reporting\BookingRevenuePeriodCalculator;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class BookingRevenueChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ReadsReportFilters;

    protected ?string $heading = 'Temp Booking Revenue';

    protected function getType(): string
    {
        return 'line';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $weeks = BookingRevenuePeriodCalculator::byWeek(
            $this->periodStart(),
            $this->periodEnd(),
            $this->filterConsultantId(),
            $this->filterClientId(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $weeks->pluck('revenue')->all(),
                    'borderColor' => '#22c55e',
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => 'Cost',
                    'data' => $weeks->pluck('cost')->all(),
                    'borderColor' => '#f97316',
                    'backgroundColor' => '#f97316',
                ],
                [
                    'label' => 'Margin',
                    'data' => $weeks->pluck('margin')->all(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $weeks->map(fn (array $week): string => $week['weekStart']->format('d M'))->all(),
        ];
    }
}
