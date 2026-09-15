<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityType;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Support\CandidateSummaryAction;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\JobStatus;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyCandidateMatch;
use App\Models\VacancyPlacement;
use App\Services\Booking\BookingEligibility;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class VacancyMatchesTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?Vacancy $record = null;

    public function mount(?Vacancy $record = null): void
    {
        $this->record = $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn () => $this->record->matches()->with('candidate'))
            ->defaultSort('score', 'desc')
            ->columns([
                TextColumn::make('score')
                    ->label('Match')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 70 => 'success',
                        $state >= 40 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->sortable(),
                TextColumn::make('candidate.first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (VacancyCandidateMatch $record): string => trim("{$record->candidate?->first_name} {$record->candidate?->last_name}") ?: '—'),
                TextColumn::make('candidate.email')
                    ->label('Email')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Matched')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                SelectColumn::make('job_status_id')
                    ->label('Status')
                    ->placeholder('Not in pipeline')
                    ->options(fn (): array => JobStatus::query()
                        ->where('company_id', $this->record->company_id)
                        ->where('industry_id', $this->record->industry_id)
                        ->ordered()
                        ->pluck('name', 'id')
                        ->toArray())
                    ->getStateUsing(fn (VacancyCandidateMatch $record): ?int => $this->applicationFor($record)?->job_status_id)
                    ->updateStateUsing(function (VacancyCandidateMatch $record, mixed $state): void {
                        VacancyApplication::updateOrCreate([
                            'vacancy_id' => $this->record->id,
                            'candidate_type' => $record->candidate_type,
                            'candidate_id' => $record->candidate_id,
                        ], [
                            'match_strength' => $record->score,
                            'job_status_id' => $state,
                        ]);

                        $candidateName = trim("{$record->candidate?->first_name} {$record->candidate?->last_name}") ?: 'Candidate';
                        $statusName = JobStatus::withoutGlobalScope('company')->find($state)?->name ?? 'no status';

                        $this->record->activities()->create([
                            'user_id' => Auth::id(),
                            'type' => ActivityType::Note->value,
                            'note' => "Moved to {$statusName}: {$candidateName}",
                            'contacted' => false,
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('markPlaced')
                    ->label('Mark as Placed')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (VacancyCandidateMatch $record): bool => ! $this->record->isTemp() && ! $this->isPlaced($record))
                    ->schema([
                        TextInput::make('actual_salary')
                            ->label('Actual Salary')
                            ->numeric()
                            ->prefix('£')
                            ->required(),
                    ])
                    ->action(function (VacancyCandidateMatch $record, array $data): void {
                        $reason = BookingEligibility::disallowedJobTitleReason($record->candidate, $this->record->job_title_id);

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
                            'candidate_type' => $record->candidate_type,
                            'candidate_id' => $record->candidate_id,
                            'actual_salary' => $data['actual_salary'],
                            'placed_at' => now(),
                        ]);

                        // Keep the Applicants board's card (if one exists,
                        // or create one) consistent with the placement this
                        // just made — otherwise a candidate placed straight
                        // from Matches shows up in the wrong column, or not
                        // at all, on the board.
                        if ($filledStatusId = $this->filledStatusId()) {
                            VacancyApplication::updateOrCreate([
                                'vacancy_id' => $this->record->id,
                                'candidate_type' => $record->candidate_type,
                                'candidate_id' => $record->candidate_id,
                            ], [
                                'match_strength' => $record->score,
                                'job_status_id' => $filledStatusId,
                            ]);
                        }

                        $candidateName = trim("{$record->candidate?->first_name} {$record->candidate?->last_name}") ?: 'Candidate';

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
                    }),
                CandidateSummaryAction::make(fn (VacancyCandidateMatch $record) => $record->candidate),
                Action::make('viewCandidate')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (VacancyCandidateMatch $record): ?string => match ($record->candidate_type) {
                        EducationCandidate::class => EducationCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        HealthcareCandidate::class => HealthcareCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        default => null,
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (VacancyCandidateMatch $record): bool => $record->candidate !== null),
            ])
            ->emptyStateHeading('No matches yet')
            ->emptyStateDescription('Run a match from this vacancy\'s edit page to rank your candidate pool against it.');
    }

    private function applicationFor(VacancyCandidateMatch $record): ?VacancyApplication
    {
        return VacancyApplication::query()
            ->where('vacancy_id', $this->record->id)
            ->where('candidate_type', $record->candidate_type)
            ->where('candidate_id', $record->candidate_id)
            ->first();
    }

    private function isPlaced(VacancyCandidateMatch $record): bool
    {
        return VacancyPlacement::query()
            ->where('vacancy_id', $this->record->id)
            ->where('candidate_type', $record->candidate_type)
            ->where('candidate_id', $record->candidate_id)
            ->exists();
    }

    private function filledStatusId(): ?int
    {
        return JobStatus::query()
            ->where('company_id', $this->record->company_id)
            ->where('industry_id', $this->record->industry_id)
            ->where('is_filled_status', true)
            ->ordered()
            ->value('id');
    }
}
