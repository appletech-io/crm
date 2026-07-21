<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityType;
use App\Models\CandidateActivity;
use App\Models\ClientActivity;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class HealthcareConsultantKpiOverview extends StatsOverviewWidget
{
    protected string $view = 'filament.widgets.healthcare-consultant-kpi-overview';

    protected static ?int $sort = 1;

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

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $stats = $this->monthStats();

        return [
            Stat::make('Calls This Month', $stats['calls']),
            Stat::make('Meetings This Month', $stats['meetings']),
            Stat::make('Applications Completed This Month', $stats['completedApplications']),
        ];
    }

    /** @return int | array<string, ?int> | null */
    protected function getColumns(): int|array|null
    {
        return 3;
    }

    /** @return array{calls: int, meetings: int, completedApplications: int} */
    public function monthStats(): array
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $consultantId = $this->activeConsultantId();
        $companyUserIds = User::query()->pluck('id');

        $calls = $this->activityCount(ActivityType::Call, $start, $end, $consultantId, $companyUserIds);
        $meetings = $this->activityCount(ActivityType::Meeting, $start, $end, $consultantId, $companyUserIds);

        $completedApplications = HealthcareCandidate::query()
            ->when($consultantId, fn ($query) => $query->where('consultant_id', $consultantId))
            ->whereHas('application', function ($query) use ($start, $end): void {
                $query->where('status', 'completed')
                    ->whereBetween('completed_at', [$start, $end]);
            })
            ->count();

        return [
            'calls' => $calls,
            'meetings' => $meetings,
            'completedApplications' => $completedApplications,
        ];
    }

    private function activeConsultantId(): ?int
    {
        if ($this->isAdmin()) {
            return $this->consultantId;
        }

        return Auth::id();
    }

    /** @param  Collection<int, int>  $companyUserIds */
    private function activityCount(ActivityType $type, Carbon $start, Carbon $end, ?int $consultantId, $companyUserIds): int
    {
        $candidateActivities = CandidateActivity::query()
            ->where('type', $type->value)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('user_id', $companyUserIds)
            ->when($consultantId, fn ($query) => $query->where('user_id', $consultantId))
            ->count();

        $clientActivities = ClientActivity::query()
            ->where('type', $type->value)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('user_id', $companyUserIds)
            ->when($consultantId, fn ($query) => $query->where('user_id', $consultantId))
            ->count();

        return $candidateActivities + $clientActivities;
    }
}
