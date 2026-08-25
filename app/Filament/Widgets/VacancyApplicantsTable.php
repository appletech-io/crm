<?php

namespace App\Filament\Widgets;

use App\Actions\Candidates\CandidateCreated;
use App\Actions\Candidates\HealthcareCandidateCreated;
use App\Enums\ActivityType;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Support\CandidateSummaryAction;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyPlacement;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class VacancyApplicantsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?Vacancy $record = null;

    public bool $onlyShortlisted = false;

    public function mount(?Vacancy $record = null, bool $onlyShortlisted = false): void
    {
        $this->record = $record;
        $this->onlyShortlisted = $onlyShortlisted;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(fn () => $this->onlyShortlisted
                ? $this->record->applications()->with('candidate')->whereNotNull('shortlisted_at')
                : $this->record->applications()->with('candidate'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('shortlisted')
                    ->label('Shortlisted')
                    ->boolean()
                    ->state(fn (VacancyApplication $record): bool => $record->isShortlisted()),
                TextColumn::make('candidate.first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (VacancyApplication $record): string => trim("{$record->candidate?->first_name} {$record->candidate?->last_name}") ?: '—'),
                TextColumn::make('candidate.email')
                    ->label('Email')
                    ->placeholder('—'),
                TextColumn::make('match_strength')
                    ->label('Match')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 70 => 'success',
                        $state >= 40 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state}%" : '—'),
                TextColumn::make('created_at')
                    ->label('Applied')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters($this->onlyShortlisted ? [] : [
                TernaryFilter::make('shortlisted_at')
                    ->label('Shortlisted')
                    ->nullable()
                    ->trueLabel('Shortlisted')
                    ->falseLabel('Not shortlisted'),
            ])
            ->recordActions([
                Action::make('toggleShortlist')
                    ->label(fn (VacancyApplication $record): string => $record->isShortlisted() ? 'Remove from Shortlist' : 'Shortlist')
                    ->icon(fn (VacancyApplication $record): string => $record->isShortlisted() ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color(fn (VacancyApplication $record): string => $record->isShortlisted() ? 'warning' : 'gray')
                    ->action(function (VacancyApplication $record): void {
                        $shortlisting = ! $record->isShortlisted();

                        $record->update(['shortlisted_at' => $shortlisting ? now() : null]);

                        $candidateName = trim("{$record->candidate?->first_name} {$record->candidate?->last_name}") ?: 'Candidate';

                        $this->record->activities()->create([
                            'user_id' => Auth::id(),
                            'type' => ActivityType::Note->value,
                            'note' => $shortlisting
                                ? "Shortlisted: {$candidateName}"
                                : "Removed from shortlist: {$candidateName}",
                            'contacted' => false,
                        ]);
                    }),
                Action::make('sendApplicationForm')
                    ->label('Send Application Form')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Send the full application form to this candidate by email?')
                    ->visible(fn (VacancyApplication $record): bool => $record->isShortlisted()
                        && $record->candidate !== null
                        && ! $record->candidate->application)
                    ->action(function (VacancyApplication $record): void {
                        match ($record->candidate_type) {
                            EducationCandidate::class => CandidateCreated::run($record->candidate, true),
                            HealthcareCandidate::class => HealthcareCandidateCreated::run($record->candidate, true),
                            default => null,
                        };

                        Notification::make()
                            ->success()
                            ->title('Application form sent')
                            ->send();
                    }),
                Action::make('markPlaced')
                    ->label('Mark as Placed')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (VacancyApplication $record): bool => ! $this->record->isTemp() && ! $this->isPlaced($record))
                    ->schema([
                        TextInput::make('actual_salary')
                            ->label('Actual Salary')
                            ->numeric()
                            ->prefix('£')
                            ->required(),
                    ])
                    ->action(function (VacancyApplication $record, array $data): void {
                        VacancyPlacement::create([
                            'vacancy_id' => $this->record->id,
                            'candidate_type' => $record->candidate_type,
                            'candidate_id' => $record->candidate_id,
                            'actual_salary' => $data['actual_salary'],
                            'placed_at' => now(),
                        ]);

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
                Action::make('createBooking')
                    ->label('Create Booking')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->visible(fn (VacancyApplication $record): bool => $this->record->isTemp() && $record->isShortlisted())
                    ->url(fn (VacancyApplication $record): string => BookingResource::getUrl('create', [
                        'candidate_id' => $record->candidate_id,
                        'client_id' => $this->record->client_id,
                        'job_title_id' => $this->record->job_title_id,
                        'dates' => $this->coverDates(),
                    ])),
                CandidateSummaryAction::make(fn (VacancyApplication $record) => $record->candidate),
                Action::make('viewCandidate')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (VacancyApplication $record): ?string => match ($record->candidate_type) {
                        EducationCandidate::class => EducationCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        HealthcareCandidate::class => HealthcareCandidateResource::getUrl('edit', ['record' => $record->candidate_id]),
                        default => null,
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (VacancyApplication $record): bool => $record->candidate !== null),
            ])
            ->emptyStateHeading($this->onlyShortlisted ? 'No candidates shortlisted yet' : 'No applicants yet')
            ->emptyStateDescription($this->onlyShortlisted
                ? 'Use the Shortlist action on the Applicants tab to add candidates here.'
                : 'Applications submitted via the public application link will appear here.');
    }

    private function isPlaced(VacancyApplication $record): bool
    {
        return VacancyPlacement::query()
            ->where('vacancy_id', $this->record->id)
            ->where('candidate_type', $record->candidate_type)
            ->where('candidate_id', $record->candidate_id)
            ->exists();
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
