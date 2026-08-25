<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityType;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Models\CandidateActivity;
use App\Models\ClientActivity;
use App\Models\EducationCandidate;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class EducationConsultantKpiOverview extends StatsOverviewWidget implements HasActions
{
    use InteractsWithActions;

    protected string $view = 'filament.widgets.education-consultant-kpi-overview';

    protected static ?int $sort = 1;

    public ?int $consultantId = null;

    public function isAdmin(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    /**
     * Consultant selection lives on the Performance Summary widget's
     * dropdown — the only one on the dashboard — so this just follows it.
     */
    #[On('dashboard-consultant-changed')]
    public function onDashboardConsultantChanged(?int $consultantId): void
    {
        $this->consultantId = $consultantId;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $stats = $this->monthStats();

        return [
            Stat::make('Calls This Month', $stats['calls'])
                ->extraAttributes($this->clickableStatAttributes(ActivityType::Call)),
            Stat::make('Meetings This Month', $stats['meetings'])
                ->extraAttributes($this->clickableStatAttributes(ActivityType::Meeting)),
            Stat::make('Applications Completed This Month', $stats['completedApplications'])
                ->description("({$stats['previousMonthCompletedApplications']})")
                ->extraAttributes([
                    'class' => 'cursor-pointer transition hover:opacity-75',
                    'wire:click' => "mountAction('viewCompletedApplications')",
                ]),
        ];
    }

    /** @return array<string, string> */
    private function clickableStatAttributes(ActivityType $type): array
    {
        return [
            'class' => 'cursor-pointer transition hover:opacity-75',
            'wire:click' => "mountAction('viewActivities', { type: '{$type->value}' })",
        ];
    }

    /**
     * Opens a slide-over listing the actual calls/meetings behind the
     * clicked stat — the whole team's if an admin is viewing "All
     * Consultants", just that consultant's otherwise (including a
     * non-admin, who can only ever see their own).
     */
    public function viewActivitiesAction(): Action
    {
        return Action::make('viewActivities')
            ->label(fn (array $arguments): string => ActivityType::from($arguments['type'])->label().'s this month')
            ->modalHeading(fn (array $arguments): string => ActivityType::from($arguments['type'])->label().'s this month — '.
                ($this->activeConsultantId() ? $this->consultant()?->name : 'the whole team'))
            ->slideOver()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (array $arguments) => view('filament.widgets.partials.activity-drilldown', [
                'activities' => $this->activitiesForModal(ActivityType::from($arguments['type'])),
            ]));
    }

    private function consultant(): ?User
    {
        return User::find($this->activeConsultantId());
    }

    /** @return Collection<int, array{note: ?string, created_at: Carbon, consultant: string, subject: string, kind: string}> */
    public function activitiesForModal(ActivityType $type): Collection
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        $consultantId = $this->activeConsultantId();
        $companyUserIds = User::query()->pluck('id');

        $candidateActivities = CandidateActivity::query()
            ->where('type', $type->value)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('user_id', $companyUserIds)
            ->when($consultantId, fn ($query) => $query->where('user_id', $consultantId))
            ->with(['model', 'user'])
            ->get()
            ->map(fn (CandidateActivity $activity): array => [
                'note' => $activity->note,
                'created_at' => $activity->created_at,
                'consultant' => $activity->user?->name ?? 'Unknown',
                'subject' => $activity->model ? trim("{$activity->model->first_name} {$activity->model->last_name}") : 'Unknown candidate',
                'kind' => 'Candidate',
            ]);

        $clientActivities = ClientActivity::query()
            ->where('type', $type->value)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('user_id', $companyUserIds)
            ->when($consultantId, fn ($query) => $query->where('user_id', $consultantId))
            ->with(['model', 'user'])
            ->get()
            ->map(fn (ClientActivity $activity): array => [
                'note' => $activity->note,
                'created_at' => $activity->created_at,
                'consultant' => $activity->user?->name ?? 'Unknown',
                'subject' => $activity->model?->name ?? 'Unknown client',
                'kind' => 'Client',
            ]);

        return $candidateActivities->concat($clientActivities)
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Opens a slide-over listing the candidates behind the "Applications
     * Completed This Month" stat — same team-wide/consultant/self scoping
     * as {@see self::viewActivitiesAction()}.
     */
    public function viewCompletedApplicationsAction(): Action
    {
        return Action::make('viewCompletedApplications')
            ->label('Applications completed this month')
            ->modalHeading('Applications completed this month — '.
                ($this->activeConsultantId() ? $this->consultant()?->name : 'the whole team'))
            ->slideOver()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn () => view('filament.widgets.partials.completed-applications-drilldown', [
                'candidates' => $this->completedApplicationsForModal(),
            ]));
    }

    /** @return Collection<int, array{name: string, consultant: string, completed_at: ?Carbon, url: string}> */
    public function completedApplicationsForModal(): Collection
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        $consultantId = $this->activeConsultantId();

        return EducationCandidate::query()
            ->when($consultantId, fn ($query) => $query->where('consultant_id', $consultantId))
            ->whereHas('application', function ($query) use ($start, $end): void {
                $query->where('status', 'completed')
                    ->whereBetween('completed_at', [$start, $end]);
            })
            ->with(['application', 'consultant'])
            ->get()
            ->map(fn (EducationCandidate $candidate): array => [
                'name' => trim("{$candidate->first_name} {$candidate->last_name}"),
                'consultant' => $candidate->consultant?->name ?? 'Unassigned',
                'completed_at' => $candidate->application?->completed_at,
                'url' => EducationCandidateResource::getUrl('edit', ['record' => $candidate]),
            ])
            ->sortByDesc('completed_at')
            ->values();
    }

    /** @return int | array<string, ?int> | null */
    protected function getColumns(): int|array|null
    {
        return 3;
    }

    /** @return array{calls: int, meetings: int, completedApplications: int, previousMonthCompletedApplications: int} */
    public function monthStats(): array
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $previousStart = Carbon::now()->startOfMonth()->subMonth();
        $previousEnd = $previousStart->copy()->endOfMonth();

        $consultantId = $this->activeConsultantId();
        $companyUserIds = User::query()->pluck('id');

        $calls = $this->activityCount(ActivityType::Call, $start, $end, $consultantId, $companyUserIds);
        $meetings = $this->activityCount(ActivityType::Meeting, $start, $end, $consultantId, $companyUserIds);

        $completedApplications = $this->completedApplicationsCount($start, $end, $consultantId);
        $previousMonthCompletedApplications = $this->completedApplicationsCount($previousStart, $previousEnd, $consultantId);

        return [
            'calls' => $calls,
            'meetings' => $meetings,
            'completedApplications' => $completedApplications,
            'previousMonthCompletedApplications' => $previousMonthCompletedApplications,
        ];
    }

    private function completedApplicationsCount(Carbon $start, Carbon $end, ?int $consultantId): int
    {
        return EducationCandidate::query()
            ->when($consultantId, fn ($query) => $query->where('consultant_id', $consultantId))
            ->whereHas('application', function ($query) use ($start, $end): void {
                $query->where('status', 'completed')
                    ->whereBetween('completed_at', [$start, $end]);
            })
            ->count();
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
