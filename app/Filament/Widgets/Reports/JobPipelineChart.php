<?php

namespace App\Filament\Widgets\Reports;

use App\Filament\Support\StatusColorPalette;
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
                    'backgroundColor' => $statuses->map(fn (JobStatus $status): string => StatusColorPalette::hexFor($status->color))->all(),
                ],
            ],
            'labels' => $statuses->pluck('name')->all(),
        ];
    }
}
