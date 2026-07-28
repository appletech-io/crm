<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

abstract class ComplianceKpiOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.compliance-kpi-overview';

    /** @return class-string<Model> */
    abstract protected function candidateModelClass(): string;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        return [
            Stat::make('Through to Live This Week', $this->throughToLiveThisWeekCount()),
            Stat::make('Outstanding in Vetting', $this->outstandingInVettingCount()),
            Stat::make('Average Time to Live', $this->formatDays($this->averageDaysToLive())),
            Stat::make('Application to Compliance Complete', $this->formatDays($this->averageDaysFromComplianceCompletionToLive())),
        ];
    }

    /** @return int | array<string, ?int> | null */
    protected function getColumns(): int|array|null
    {
        return 4;
    }

    public function throughToLiveThisWeekCount(): int
    {
        $modelClass = $this->candidateModelClass();
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        return $modelClass::query()
            ->whereHas('statuses', function (Builder $query) use ($start, $end): void {
                $query->whereBetween('created_at', [$start, $end])
                    ->whereHas('status', fn (Builder $query) => $query->where('name', 'Live'));
            })
            ->count();
    }

    public function outstandingInVettingCount(): int
    {
        $modelClass = $this->candidateModelClass();

        return $modelClass::query()
            ->whereHas('statuses.status', fn (Builder $query) => $query->where('name', 'Vetting'))
            ->count();
    }

    /**
     * There's no separate "went live" event to measure against — compliance_completed_at
     * is what marks a candidate as live.
     */
    public function averageDaysToLive(): ?float
    {
        $modelClass = $this->candidateModelClass();

        $durationsInDays = $modelClass::query()
            ->whereNotNull('compliance_completed_at')
            ->get(['created_at', 'compliance_completed_at'])
            ->map(fn (Model $candidate): float => $candidate->created_at->diffInMinutes($candidate->compliance_completed_at) / 1440);

        if ($durationsInDays->isEmpty()) {
            return null;
        }

        return round($durationsInDays->average(), 1);
    }

    public function averageDaysFromComplianceCompletionToLive(): ?float
    {
        $modelClass = $this->candidateModelClass();

        $durationsInDays = $modelClass::query()
            ->whereNotNull('compliance_completed_at')
            ->with('application')
            ->get(['id', 'compliance_completed_at'])
            ->map(function (Model $candidate): ?float {
                $applicationCompletedAt = $candidate->application?->completed_at;

                return $applicationCompletedAt
                    ? $applicationCompletedAt->diffInMinutes($candidate->compliance_completed_at) / 1440
                    : null;
            })
            ->filter(fn (?float $duration): bool => $duration !== null);

        if ($durationsInDays->isEmpty()) {
            return null;
        }

        return round($durationsInDays->average(), 1);
    }

    protected function formatDays(?float $days): string
    {
        return $days === null ? 'N/A' : "$days days";
    }
}
