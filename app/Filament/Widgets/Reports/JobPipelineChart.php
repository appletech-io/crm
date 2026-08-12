<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Widgets\Reports\Concerns\ReadsReportFilters;
use App\Models\JobStatus;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * A right-now snapshot of open work, not a trend — deliberately ignores the
 * page's date range (a vacancy's current status has no single "date" of its
 * own) but still respects the consultant/client filters.
 */
class JobPipelineChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ReadsReportFilters;

    protected ?string $heading = 'Job Pipeline';

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $statuses = JobStatus::query()
            ->where('industry_id', active_industry_id())
            ->withCount(['vacancies' => function (Builder $query): void {
                $query->forActiveIndustry();

                if ($consultantId = $this->filterConsultantId()) {
                    $query->where('consultant_id', $consultantId);
                }

                if ($clientId = $this->filterClientId()) {
                    $query->where('client_id', $clientId);
                }
            }])
            ->get();

        return [
            'datasets' => [
                [
                    'data' => $statuses->pluck('vacancies_count')->all(),
                    'backgroundColor' => $statuses->map(fn (JobStatus $status): string => $this->hexFor($status->color))->all(),
                ],
            ],
            'labels' => $statuses->pluck('name')->all(),
        ];
    }

    private function hexFor(?string $color): string
    {
        return match ($color) {
            'red' => '#ef4444',
            'orange' => '#f97316',
            'amber' => '#f59e0b',
            'yellow' => '#eab308',
            'lime' => '#84cc16',
            'green' => '#22c55e',
            'emerald' => '#10b981',
            'teal' => '#14b8a6',
            'cyan' => '#06b6d4',
            'sky' => '#0ea5e9',
            'blue' => '#3b82f6',
            'indigo' => '#6366f1',
            'violet' => '#8b5cf6',
            'purple' => '#a855f7',
            'fuchsia' => '#d946ef',
            'pink' => '#ec4899',
            'rose' => '#f43f5e',
            default => '#94a3b8',
        };
    }
}
