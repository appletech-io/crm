<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\ReadsReportFilters;
use App\Services\Reporting\BookingRevenuePeriodCalculator;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

        $projected = BookingRevenuePeriodCalculator::projectCurrentWeek($weeks, $this->periodEnd());

        $datasets = [
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
        ];

        if ($projected !== null) {
            $datasets[] = $this->projectedDataset('Revenue (projected)', '#22c55e', 'revenue', $weeks, $projected);
            $datasets[] = $this->projectedDataset('Cost (projected)', '#f97316', 'cost', $weeks, $projected);
            $datasets[] = $this->projectedDataset('Margin (projected)', '#3b82f6', 'margin', $weeks, $projected);
        }

        return [
            'datasets' => $datasets,
            'labels' => $weeks->map(fn (array $week): string => $week['weekStart']->format('d M'))->all(),
        ];
    }

    /**
     * A dotted line covering just the last two points: it starts from the
     * last complete week's actual value (so it visually connects to the
     * solid line) and ends at the projected full-week figure for the
     * in-progress week.
     *
     * @param  Collection<int, array{weekStart: Carbon, revenue: float, cost: float, margin: float, bookings: int}>  $weeks
     * @param  array{revenue: float, cost: float, margin: float}  $projected
     * @return array<string, mixed>
     */
    private function projectedDataset(string $label, string $color, string $key, Collection $weeks, array $projected): array
    {
        $lastIndex = $weeks->count() - 1;

        $data = array_fill(0, $weeks->count(), null);
        $data[$lastIndex] = $projected[$key];

        if ($lastIndex > 0) {
            $data[$lastIndex - 1] = $weeks[$lastIndex - 1][$key];
        }

        $pointRadius = array_fill(0, $weeks->count(), 0);
        $pointRadius[$lastIndex] = 4;

        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => $color,
            'backgroundColor' => 'transparent',
            'borderDash' => [6, 6],
            'pointRadius' => $pointRadius,
        ];
    }
}
