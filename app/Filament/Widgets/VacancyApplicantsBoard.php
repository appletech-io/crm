<?php

namespace App\Filament\Widgets;

use App\Actions\Candidates\CandidateCreated;
use App\Actions\Candidates\HealthcareCandidateCreated;
use App\Enums\ActivityType;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\JobStatus;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyPlacement;
use App\Services\Booking\BookingEligibility;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * A Trello-style board for the applicants of a single vacancy — columns are
 * this company/industry's JobStatus pipeline ({@see JobStatus::scopeOrdered()},
 * the same statuses managed under Job Statuses and visualised job-by-job on
 * the Job Pipeline page), and dragging a candidate's card between columns
 * moves it through that pipeline. Replaces the old Applicants/Shortlisted
 * tabs and their shortlist/view row actions with this single view — see
 * VacancyForm.
 */
class VacancyApplicantsBoard extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.vacancy-applicants-board';

    protected int|string|array $columnSpan = 'full';

    public ?Vacancy $record = null;

    public function mount(?Vacancy $record = null): void
    {
        $this->record = $record;
    }

    /** @return Collection<int, JobStatus> */
    public function statuses(): Collection
    {
        return JobStatus::query()
            ->where('company_id', $this->record->company_id)
            ->where('industry_id', $this->record->industry_id)
            ->ordered()
            ->get();
    }

    /** @return Collection<int, Collection<int, VacancyApplication>> keyed by job_status_id */
    public function applicationsByStatus(): Collection
    {
        return $this->record->applications()
            ->with('candidate')
            ->get()
            ->groupBy('job_status_id');
    }

    private function filledStatusId(): ?int
    {
        return $this->statuses()->firstWhere('is_filled_status', true)?->id;
    }

    /**
     * Moves a candidate's card to a different column. Application and status
     * are both re-scoped to this vacancy's own company here rather than
     * trusted from the client, since the ids driving this arrive from a
     * drag-and-drop event rather than a validated form.
     */
    public function moveApplication(int $applicationId, int $jobStatusId): void
    {
        $application = $this->record->applications()->with('candidate')->find($applicationId);

        $status = JobStatus::query()
            ->where('company_id', $this->record->company_id)
            ->where('industry_id', $this->record->industry_id)
            ->find($jobStatusId);

        if (! $application || ! $status || $application->job_status_id === $status->id) {
            return;
        }

        $application->update(['job_status_id' => $status->id]);

        $candidateName = $this->candidateName($application);

        $this->record->activities()->create([
            'user_id' => Auth::id(),
            'type' => ActivityType::Note->value,
            'note' => "Moved to {$status->name}: {$candidateName}",
            'contacted' => false,
        ]);

        if ($status->is_filled_status && ! $this->record->isTemp() && ! $this->isPlaced($application)) {
            Notification::make()
                ->warning()
                ->title("Don't forget to record {$candidateName}'s placement to log their salary.")
                ->send();
        }
    }

    /**
     * Lets a consultant put any candidate from their pool straight onto this
     * board, rather than only ever via a public application or a Matches
     * row — e.g. someone they already know is right for the role. Left
     * without a job_status_id so VacancyApplicationObserver lands it in the
     * pipeline's first column, same as any other new application.
     */
    public function addCandidateAction(): Action
    {
        return Action::make('addCandidate')
            ->label('Add Candidate')
            ->icon('heroicon-o-user-plus')
            ->schema([
                Select::make('candidate_id')
                    ->label('Candidate')
                    ->options(fn (): array => $this->addableCandidateOptions())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $candidateModelClass = $this->record->industry?->candidateModel();

                if (! $candidateModelClass) {
                    return;
                }

                $application = VacancyApplication::firstOrCreate([
                    'vacancy_id' => $this->record->id,
                    'candidate_type' => $candidateModelClass,
                    'candidate_id' => $data['candidate_id'],
                ]);

                $application->load('candidate');
                $candidateName = $this->candidateName($application);

                $this->record->activities()->create([
                    'user_id' => Auth::id(),
                    'type' => ActivityType::Note->value,
                    'note' => "Added to pipeline: {$candidateName}",
                    'contacted' => false,
                ]);

                Notification::make()
                    ->success()
                    ->title("{$candidateName} added to the pipeline")
                    ->send();
            });
    }

    /** @return array<int, string> */
    private function addableCandidateOptions(): array
    {
        $candidateModelClass = $this->record->industry?->candidateModel();

        if (! $candidateModelClass) {
            return [];
        }

        $alreadyApplied = $this->record->applications()
            ->where('candidate_type', $candidateModelClass)
            ->pluck('candidate_id');

        return $candidateModelClass::query()
            ->where('company_id', $this->record->company_id)
            ->when(
                $candidateModelClass === Candidate::class,
                fn ($query) => $query->where('industry_id', $this->record->industry_id),
            )
            ->whereNotIn('id', $alreadyApplied)
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (EloquentModel $candidate): array => [
                $candidate->id => trim("{$candidate->first_name} {$candidate->last_name}"),
            ])
            ->all();
    }

    public function isPlaced(VacancyApplication $application): bool
    {
        return VacancyPlacement::query()
            ->where('vacancy_id', $this->record->id)
            ->where('candidate_type', $application->candidate_type)
            ->where('candidate_id', $application->candidate_id)
            ->exists();
    }

    /**
     * Every new application defaults into the first column (see
     * VacancyApplicationObserver), so "has a status" alone can't stand in
     * for the old shortlisted_at toggle the way it can elsewhere on this
     * board — these two actions instead need the candidate to have been
     * moved at least one column along, the drag-and-drop equivalent of the
     * old explicit shortlist step.
     */
    public function canSendApplicationForm(VacancyApplication $application): bool
    {
        return $this->hasMovedPastFirstStage($application)
            && $application->candidate !== null
            && ! $application->candidate->application;
    }

    public function canCreateBooking(VacancyApplication $application): bool
    {
        return $this->record->isTemp() && $this->hasMovedPastFirstStage($application);
    }

    private function hasMovedPastFirstStage(VacancyApplication $application): bool
    {
        return $application->job_status_id !== null
            && $application->job_status_id !== $this->statuses()->first()?->id;
    }

    public function canMarkPlaced(VacancyApplication $application): bool
    {
        return ! $this->record->isTemp() && ! $this->isPlaced($application);
    }

    public function canEditSalary(VacancyApplication $application): bool
    {
        return ! $this->record->isTemp() && $this->isPlaced($application);
    }

    /** @param  array<string, mixed>  $arguments */
    private function applicationForArguments(array $arguments): ?VacancyApplication
    {
        if (! isset($arguments['applicationId'])) {
            return null;
        }

        return $this->record->applications()->with('candidate')->find($arguments['applicationId']);
    }

    private function placementFor(VacancyApplication $application): ?VacancyPlacement
    {
        return VacancyPlacement::query()
            ->where('vacancy_id', $this->record->id)
            ->where('candidate_type', $application->candidate_type)
            ->where('candidate_id', $application->candidate_id)
            ->first();
    }

    public function candidateProfileUrl(VacancyApplication $application): ?string
    {
        return match ($application->candidate_type) {
            EducationCandidate::class => EducationCandidateResource::getUrl('edit', ['record' => $application->candidate_id]),
            HealthcareCandidate::class => HealthcareCandidateResource::getUrl('edit', ['record' => $application->candidate_id]),
            Candidate::class => CandidateResource::getUrl('edit', ['record' => $application->candidate_id]),
            default => null,
        };
    }

    public function createBookingUrl(VacancyApplication $application): string
    {
        return BookingResource::getUrl('create', [
            'candidate_id' => $application->candidate_id,
            'client_id' => $this->record->client_id,
            'job_title_id' => $this->record->job_title_id,
            'dates' => $this->coverDates(),
        ]);
    }

    public function sendApplicationFormAction(): Action
    {
        return Action::make('sendApplicationForm')
            ->label('Send Application Form')
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Send the full application form to this candidate by email?')
            ->visible(fn (array $arguments): bool => $this->applicationForArguments($arguments) !== null
                && $this->canSendApplicationForm($this->applicationForArguments($arguments)))
            ->action(function (array $arguments): void {
                $application = $this->applicationForArguments($arguments);

                if (! $application || ! $application->candidate) {
                    return;
                }

                match ($application->candidate_type) {
                    EducationCandidate::class => CandidateCreated::run($application->candidate, true),
                    HealthcareCandidate::class => HealthcareCandidateCreated::run($application->candidate, true),
                    default => null,
                };

                Notification::make()
                    ->success()
                    ->title('Application form sent')
                    ->send();
            });
    }

    public function markPlacedAction(): Action
    {
        return Action::make('markPlaced')
            ->label('Mark as Placed')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (array $arguments): bool => $this->applicationForArguments($arguments) !== null
                && $this->canMarkPlaced($this->applicationForArguments($arguments)))
            ->schema([
                TextInput::make('actual_salary')
                    ->label('Actual Salary')
                    ->numeric()
                    ->prefix('£')
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $application = $this->applicationForArguments($arguments);

                if (! $application) {
                    return;
                }

                $reason = BookingEligibility::disallowedJobTitleReason($application->candidate, $this->record->job_title_id);

                if ($reason) {
                    Notification::make()
                        ->danger()
                        ->title('Cannot mark as placed')
                        ->body($reason)
                        ->send();

                    return;
                }

                VacancyPlacement::create([
                    'vacancy_id' => $this->record->id,
                    'candidate_type' => $application->candidate_type,
                    'candidate_id' => $application->candidate_id,
                    'actual_salary' => $data['actual_salary'],
                    'placed_at' => now(),
                ]);

                // Keep the card's column consistent with the placement it
                // now has — without this it stays wherever it was dragged
                // to before being marked placed, which reads as "not
                // actually placed" on the board.
                if ($filledStatusId = $this->filledStatusId()) {
                    $application->update(['job_status_id' => $filledStatusId]);
                }

                $candidateName = $this->candidateName($application);

                $this->record->activities()->create([
                    'user_id' => Auth::id(),
                    'type' => ActivityType::Note->value,
                    'note' => "Marked as placed: {$candidateName}",
                    'contacted' => false,
                ]);

                Notification::make()
                    ->success()
                    ->title('Candidate marked as placed')
                    ->send();
            });
    }

    public function editSalaryAction(): Action
    {
        return Action::make('editSalary')
            ->label('Edit Salary')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (array $arguments): bool => $this->applicationForArguments($arguments) !== null
                && $this->canEditSalary($this->applicationForArguments($arguments)))
            ->schema([
                TextInput::make('actual_salary')
                    ->label('Actual Salary')
                    ->numeric()
                    ->prefix('£')
                    ->required(),
            ])
            ->fillForm(function (array $arguments): array {
                $application = $this->applicationForArguments($arguments);

                return ['actual_salary' => $application ? $this->placementFor($application)?->actual_salary : null];
            })
            ->action(function (array $arguments, array $data): void {
                $application = $this->applicationForArguments($arguments);

                if (! $application) {
                    return;
                }

                $this->placementFor($application)?->update(['actual_salary' => $data['actual_salary']]);
            });
    }

    private function candidateName(VacancyApplication $application): string
    {
        return trim("{$application->candidate?->first_name} {$application->candidate?->last_name}") ?: 'Candidate';
    }

    /**
     * Every date between this vacancy's cover start/end dates, passed to
     * Create Booking as its "dates" prefill so the booking's day schedule is
     * generated for the whole range rather than a single day. Empty when
     * either date isn't set — the consultant then just picks dates fresh on
     * the booking form.
     *
     * @return array<int, string>
     */
    private function coverDates(): array
    {
        if (! $this->record->start_date || ! $this->record->end_date) {
            return [];
        }

        return collect(CarbonPeriod::create($this->record->start_date, $this->record->end_date))
            ->map(fn ($date): string => $date->toDateString())
            ->all();
    }
}
