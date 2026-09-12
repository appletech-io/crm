<?php

namespace App\Filament\Widgets;

use App\Enums\VacancyEmploymentType;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyPlacement;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

/**
 * The contract/perm equivalent of {@see ConsultantPerformanceSummary} — for
 * an industry with no Bookings, "this week's gross profit/days out/rebook
 * rate" has no meaning, so this tracks the Vacancy → Application →
 * Placement pipeline instead. It also owns the dashboard's consultant
 * dropdown (mirroring ConsultantPerformanceSummary's own), since an
 * industry with no bookings has no other widget to host it — {@see
 * GenericConsultantKpiOverview} still just follows whichever widget
 * dispatches "dashboard-consultant-changed".
 */
class ItPlacementsOverview extends StatsOverviewWidget
{
    protected string $view = 'filament.widgets.it-placements-overview';

    protected static ?int $sort = 0;

    public ?int $consultantId = null;

    public function isAdmin(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    /** @return array<int, string> */
    public function consultantOptions(): array
    {
        return User::role('consultant')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function updatedConsultantId(): void
    {
        $this->dispatch('dashboard-consultant-changed', consultantId: $this->consultantId);
    }

    #[On('dashboard-consultant-changed')]
    public function onDashboardConsultantChanged(?int $consultantId): void
    {
        $this->consultantId = $consultantId;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $stats = $this->pipelineStats();

        return [
            Stat::make('Open Vacancies', $stats['openVacancies'])
                ->icon('heroicon-o-briefcase')
                ->color('info'),
            Stat::make('Applications This Month', $stats['applications'])
                ->description("({$stats['previousMonthApplications']} last month)")
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('warning'),
            Stat::make('Placements This Month', $stats['placements'])
                ->description("({$stats['previousMonthPlacements']} last month)")
                ->icon('heroicon-o-check-badge')
                ->color('success'),
            Stat::make('Placement Fee Pipeline', '£'.number_format($stats['feePipeline']))
                ->description('Estimated, across open permanent roles')
                ->icon('heroicon-o-banknotes')
                ->color('gray'),
        ];
    }

    /** @return int | array<string, ?int> | null */
    protected function getColumns(): int|array|null
    {
        return 4;
    }

    /**
     * Cached for 10 minutes, same as the widgets this sits alongside.
     *
     * @return array{openVacancies: int, applications: int, previousMonthApplications: int, placements: int, previousMonthPlacements: int, feePipeline: float}
     */
    public function pipelineStats(): array
    {
        $consultantId = $this->activeConsultantId();

        return Cache::remember(
            $this->cacheKey('pipeline-stats'),
            now()->addMinutes(10),
            function () use ($consultantId): array {
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                $previousStart = $start->copy()->subMonth();
                $previousEnd = $previousStart->copy()->endOfMonth();

                $vacancies = Vacancy::query()
                    ->forActiveIndustry()
                    ->when($consultantId, fn ($query) => $query->where('consultant_id', $consultantId))
                    ->withCount('placements')
                    ->get();

                $openVacancies = $vacancies->filter(
                    fn (Vacancy $vacancy): bool => $vacancy->placements_count < $vacancy->positions_available
                );

                $feePipeline = $openVacancies
                    ->filter(fn (Vacancy $vacancy): bool => $vacancy->employment_type !== VacancyEmploymentType::Temp)
                    ->sum(fn (Vacancy $vacancy): float => $vacancy->estimatedPlacementValue() ?? 0.0);

                return [
                    'openVacancies' => $openVacancies->count(),
                    'applications' => $this->applicationsCount($start, $end, $consultantId),
                    'previousMonthApplications' => $this->applicationsCount($previousStart, $previousEnd, $consultantId),
                    'placements' => $this->placementsCount($start, $end, $consultantId),
                    'previousMonthPlacements' => $this->placementsCount($previousStart, $previousEnd, $consultantId),
                    'feePipeline' => $feePipeline,
                ];
            },
        );
    }

    private function applicationsCount(Carbon $start, Carbon $end, ?int $consultantId): int
    {
        return VacancyApplication::query()
            ->whereHas('vacancy', function ($query) use ($consultantId): void {
                $query->forActiveIndustry();
                if ($consultantId) {
                    $query->where('consultant_id', $consultantId);
                }
            })
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private function placementsCount(Carbon $start, Carbon $end, ?int $consultantId): int
    {
        return VacancyPlacement::query()
            ->whereHas('vacancy', function ($query) use ($consultantId): void {
                $query->forActiveIndustry();
                if ($consultantId) {
                    $query->where('consultant_id', $consultantId);
                }
            })
            ->whereBetween('placed_at', [$start, $end])
            ->count();
    }

    private function activeConsultantId(): ?int
    {
        if ($this->isAdmin()) {
            return $this->consultantId;
        }

        return Auth::id();
    }

    /**
     * Scoped by company + active industry + consultant + the current
     * month, so the cache rolls over naturally into a new month.
     */
    private function cacheKey(string $suffix): string
    {
        $companyId = Auth::user()?->company_id;
        $industryId = active_industry_id();
        $consultantId = $this->activeConsultantId();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();

        return "it-placements-overview:{$companyId}:{$industryId}:{$consultantId}:{$suffix}:{$monthStart}";
    }
}
