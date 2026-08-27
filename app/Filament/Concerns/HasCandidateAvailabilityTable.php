<?php

namespace App\Filament\Concerns;

use App\Enums\CandidateAvailabilityStatus;
use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Services\Candidates\CandidateWeeklyAvailability;
use Filament\Actions\Action;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

trait HasCandidateAvailabilityTable
{
    public string $weekStart;

    abstract protected function availabilityCandidate(): EducationCandidate|HealthcareCandidate|Candidate|null;

    protected function initializeAvailabilityWeek(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function goToPreviousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function goToNextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function goToCurrentWeek(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    /**
     * The actual persistence behind the status Select on each row — pulled
     * out to a plain public method (rather than left inline on the column)
     * so it can be exercised directly, since Filament doesn't check
     * authorization on inline-editable columns automatically.
     */
    public function setAvailabilityStatus(string $date, ?string $status): void
    {
        $candidate = $this->availabilityCandidate();

        if (! $candidate) {
            return;
        }

        $row = collect(CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse($date)))
            ->firstWhere('date', $date);

        if (! $row || ! $row['editable']) {
            return;
        }

        // A plain where('date', $date) can't be trusted to match: the "date"
        // cast reads back as date-only but still writes a full datetime
        // string, so an exact string comparison against a bare Y-m-d value
        // silently fails to find the existing row.
        $existing = $candidate->availabilities()->whereDate('date', $date)->first();

        if ($status === null) {
            $existing?->delete();

            return;
        }

        if ($existing) {
            $existing->update(['status' => $status]);
        } else {
            $candidate->availabilities()->create(['date' => $date, 'status' => $status]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function availabilityRows(): array
    {
        $candidate = $this->availabilityCandidate();

        if (! $candidate) {
            return [];
        }

        return CandidateWeeklyAvailability::forWeek($candidate, Carbon::parse($this->weekStart));
    }

    protected function configureAvailabilityTable(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->availabilityRows())
            ->heading(fn (): string => 'Week commencing '.Carbon::parse($this->weekStart)->startOfWeek(Carbon::MONDAY)->format('j M Y'))
            ->headerActions([
                Action::make('previousAvailabilityWeek')
                    ->label('')
                    ->icon('heroicon-o-chevron-left')
                    ->color('gray')
                    ->action(fn () => $this->goToPreviousWeek()),
                Action::make('currentAvailabilityWeek')
                    ->label('This Week')
                    ->color('gray')
                    ->action(fn () => $this->goToCurrentWeek()),
                Action::make('nextAvailabilityWeek')
                    ->label('')
                    ->icon('heroicon-o-chevron-right')
                    ->color('gray')
                    ->action(fn () => $this->goToNextWeek()),
            ])
            ->columns([
                TextColumn::make('day_label')
                    ->label('Day'),
                SelectColumn::make('status')
                    ->label('Availability')
                    ->placeholder('Not set')
                    ->options(fn (array $record): array => $record['editable']
                        ? CandidateAvailabilityStatus::settableOptions()
                        : CandidateAvailabilityStatus::options())
                    ->disabled(fn (array $record): bool => ! $record['editable'])
                    ->updateStateUsing(function (array $record, mixed $state): mixed {
                        $this->setAvailabilityStatus($record['date'], $state);

                        return $state;
                    }),
            ])
            ->paginated(false);
    }
}
