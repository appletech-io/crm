<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CandidatePools\CandidatePoolResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Resources\Vacancies\VacancyResource;
use App\Models\Candidate;
use App\Models\CandidatePool;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\Vacancy;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * A Jobs → Candidates → Status flow, styled as a wizard-style step bar
 * (reusing Filament's own fi-sc-wizard-header classes) rather than a plain
 * chart — ordered by each JobStatus's own {@see JobStatus::scopeOrdered()}
 * sort_order. Clicking the "Jobs" step or any status step filters the
 * vacancy list rendered inline underneath, without leaving the dashboard.
 */
class JobPipelineFlow extends Widget
{
    protected string $view = 'filament.widgets.job-pipeline-flow';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    private const JOBS_LIMIT = 10;

    private const CANDIDATES_LIMIT = 10;

    public ?int $consultantId = null;

    public ?int $poolId = null;

    public ?int $selectedStatusId = null;

    public bool $viewingCandidates = false;

    public function selectStatus(?int $statusId): void
    {
        $this->selectedStatusId = $statusId;
        $this->viewingCandidates = false;
    }

    public function selectCandidates(): void
    {
        $this->viewingCandidates = true;
    }

    /**
     * Picking a pool is clearly candidate-focused intent — without this,
     * the effect is invisible whenever someone changes the dropdown while
     * still looking at the Jobs list, since pools never filter vacancies.
     */
    public function updatedPoolId(): void
    {
        $this->viewingCandidates = true;
    }

    public function isAdmin(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    #[On('dashboard-consultant-changed')]
    public function onDashboardConsultantChanged(?int $consultantId): void
    {
        $this->consultantId = $consultantId;
    }

    /** @return array<int, string> */
    public function poolOptions(): array
    {
        return CandidatePoolResource::getEloquentQuery()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function jobsCount(): int
    {
        return Vacancy::query()
            ->forActiveIndustry()
            ->when($this->consultantId, fn ($query) => $query->where('consultant_id', $this->consultantId))
            ->when($this->selectedPoolClientId(), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->count();
    }

    public function jobsUrl(): string
    {
        return VacancyResource::getUrl('index');
    }

    public function candidatesCount(): int
    {
        if ($this->poolId) {
            return $this->selectedPool()?->candidates()->count() ?? 0;
        }

        $candidateModelClass = $this->candidateModelClass();

        if (! $candidateModelClass) {
            return 0;
        }

        return $candidateModelClass::query()
            ->when(
                Industry::candidateModelIsShared($candidateModelClass),
                fn ($query) => $query->where('industry_id', active_industry_id()),
            )
            ->count();
    }

    public function candidatesUrl(): string
    {
        if ($this->poolId) {
            return CandidatePoolResource::getUrl('edit', ['record' => $this->poolId]);
        }

        return match ($this->candidateModelClass()) {
            EducationCandidate::class => EducationCandidateResource::getUrl('index'),
            HealthcareCandidate::class => HealthcareCandidateResource::getUrl('index'),
            default => CandidateResource::getUrl('index'),
        };
    }

    /**
     * The generic Candidate model is the only one with a single, fixed
     * job title to show on a card — Education/Healthcare candidates work
     * across whichever roles their skills/qualifications cover instead, so
     * eager-loading a jobTitle relation neither of those models has would
     * throw. The blade's `$candidate->jobTitle?->name` already falls back
     * to "No job title" for them without it.
     *
     * @return Collection<int, Model>
     */
    public function selectedCandidates(): Collection
    {
        $candidateModelClass = $this->candidateModelClass();
        $eagerLoadJobTitle = $candidateModelClass === Candidate::class;

        if ($this->poolId) {
            return $this->selectedPool()?->candidates()
                ->when($eagerLoadJobTitle, fn ($query) => $query->with('jobTitle'))
                ->latest()
                ->limit(self::CANDIDATES_LIMIT)
                ->get() ?? collect();
        }

        if (! $candidateModelClass) {
            return collect();
        }

        return $candidateModelClass::query()
            ->when(
                Industry::candidateModelIsShared($candidateModelClass),
                fn ($query) => $query->where('industry_id', active_industry_id()),
            )
            ->when($eagerLoadJobTitle, fn ($query) => $query->with('jobTitle'))
            ->latest()
            ->limit(self::CANDIDATES_LIMIT)
            ->get();
    }

    /** @return class-string<Model>|null */
    private function candidateModelClass(): ?string
    {
        return Industry::candidateModelForSlug(active_industry() ?? '');
    }

    /** @return Collection<int, array{status: JobStatus, open: int, total: int, url: string}> */
    public function statuses(): Collection
    {
        return JobStatus::query()
            ->where('industry_id', active_industry_id())
            ->ordered()
            ->get()
            ->map(function (JobStatus $status): array {
                $vacancies = Vacancy::query()
                    ->forActiveIndustry()
                    ->where('job_status_id', $status->id)
                    ->when($this->consultantId, fn ($query) => $query->where('consultant_id', $this->consultantId))
                    ->when($this->selectedPoolClientId(), fn ($query, int $clientId) => $query->where('client_id', $clientId))
                    ->withCount('placements')
                    ->get();

                return [
                    'status' => $status,
                    'total' => $vacancies->count(),
                    'open' => $vacancies->filter(
                        fn (Vacancy $vacancy): bool => $vacancy->placements_count < $vacancy->positions_available
                    )->count(),
                    'url' => VacancyResource::getUrl('index', [
                        'tableFilters' => ['job_status_id' => ['value' => $status->id]],
                    ]),
                ];
            });
    }

    /** @return Collection<int, Vacancy> */
    public function selectedJobs(): Collection
    {
        return Vacancy::query()
            ->forActiveIndustry()
            ->when($this->consultantId, fn ($query) => $query->where('consultant_id', $this->consultantId))
            ->when($this->selectedStatusId, fn ($query) => $query->where('job_status_id', $this->selectedStatusId))
            ->when($this->selectedPoolClientId(), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->with(['client', 'consultant', 'jobStatus'])
            ->latest()
            ->limit(self::JOBS_LIMIT)
            ->get();
    }

    public function selectedJobsCount(): int
    {
        return $this->selectedStatusId === null
            ? $this->jobsCount()
            : ($this->statuses()->firstWhere('status.id', $this->selectedStatusId)['total'] ?? 0);
    }

    public function selectedJobsUrl(): string
    {
        if ($this->selectedStatusId === null) {
            return $this->jobsUrl();
        }

        return VacancyResource::getUrl('index', [
            'tableFilters' => ['job_status_id' => ['value' => $this->selectedStatusId]],
        ]);
    }

    private function selectedPool(): ?CandidatePool
    {
        return CandidatePoolResource::getEloquentQuery()->find($this->poolId);
    }

    /**
     * A per-client pool (e.g. "BlueWave Digital Candidates" — see
     * VantagePointDemoSeeder::seedCandidatePools()) also narrows the Jobs
     * and status counts/lists to that same client's vacancies, so picking
     * one pool gives a consistent "everything for this client" view rather
     * than only filtering the Candidates step. A skill/availability pool
     * with no client_id leaves Jobs/statuses unfiltered, since there's no
     * client to narrow by.
     */
    private function selectedPoolClientId(): ?int
    {
        return $this->selectedPool()?->client_id;
    }
}
