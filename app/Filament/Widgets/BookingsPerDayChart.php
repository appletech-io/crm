<?php

namespace App\Filament\Widgets;

use App\Models\BookingDay;
use Filament\Widgets\ChartWidget;

class BookingsPerDayChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '200px';

    public ?string $filter = '2_weeks';

    private const FILTER_WEEKS = [
        '1_week' => 1,
        '2_weeks' => 2,
    ];

    public function getHeading(): string
    {
        return 'Daily Bookings';
    }

    protected function getFilters(): array
    {
        return [
            '1_week' => '1 Week',
            '2_weeks' => '2 Weeks',
            '1_month' => '1 Month',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getData(): array
    {
        $start = now()->startOfDay();
        $rangeEnd = $this->filter === '1_month'
            ? $start->copy()->addMonth()->subDay()
            : $start->copy()->addWeeks(self::FILTER_WEEKS[$this->filter] ?? self::FILTER_WEEKS['2_weeks'])->subDay();
        $totalDays = (int) $start->diffInDays($rangeEnd) + 1;

        $dayPeriods = BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->visibleToCurrentUser())
            ->whereBetween('date', [$start->toDateString(), $rangeEnd->toDateString()])
            ->get(['booking_id', 'date']);

        $labels = [];
        $counts = [];

        for ($day = 0; $day < $totalDays; $day++) {
            $date = $start->copy()->addDays($day);

            $labels[] = $date->format('d M');

            $counts[] = $dayPeriods
                ->filter(fn (BookingDay $dayPeriod): bool => $dayPeriod->date->isSameDay($date))
                ->pluck('booking_id')
                ->unique()
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $counts,
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
