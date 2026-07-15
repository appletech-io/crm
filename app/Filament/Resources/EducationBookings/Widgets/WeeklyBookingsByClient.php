<?php

namespace App\Filament\Resources\EducationBookings\Widgets;

use App\Filament\Resources\EducationBookings\BookingFilters;
use App\Filament\Resources\EducationBookings\EducationBookingResource;
use App\Models\EducationBooking;
use App\Models\EducationBookingDayPeriod;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class WeeklyBookingsByClient extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public string $weekStart;

    public function mount(): void
    {
        $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->addWeek()->toDateString();
    }

    public function goToCurrentWeek(): void
    {
        $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function weekStartDate(): Carbon
    {
        return Carbon::parse($this->weekStart);
    }

    public function weekEndDate(): Carbon
    {
        return $this->weekStartDate()->copy()->endOfWeek(Carbon::SUNDAY);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading("This Week's Bookings")
            ->description(fn (): string => $this->weekStartDate()->format('jS M').' - '.$this->weekEndDate()->format('jS M Y'))
            ->query(fn (): Builder => $this->tableQuery())
            ->recordUrl(fn (EducationBooking $record): string => EducationBookingResource::getUrl('edit', ['record' => $record]))
            ->groups([
                Group::make('education_client_id')
                    ->label('Client')
                    ->getTitleFromRecordUsing(fn (EducationBooking $record): string => $this->clientLabel($record))
                    ->collapsible(),
            ])
            ->defaultGroup('education_client_id')
            ->groupingSettingsHidden()
            ->filters([
                BookingFilters::client(),
                BookingFilters::candidate(),
                BookingFilters::consultant(),
            ])
            ->columns([
                TextColumn::make('candidate_name')
                    ->label('Candidate')
                    ->getStateUsing(fn (EducationBooking $record): string => $this->candidateLabel($record)),
                TextColumn::make('jobTitle.name')
                    ->label('Job Title')
                    ->placeholder('—'),
                ...$this->dayColumns(),
            ])
            ->headerActions([
                Action::make('previousWeek')
                    ->label('')
                    ->icon('heroicon-o-chevron-left')
                    ->action(fn () => $this->previousWeek()),
                Action::make('currentWeek')
                    ->label('This Week')
                    ->action(fn () => $this->goToCurrentWeek()),
                Action::make('nextWeek')
                    ->label('')
                    ->icon('heroicon-o-chevron-right')
                    ->action(fn () => $this->nextWeek()),
            ])
            ->paginated(false)
            ->emptyStateHeading('No bookings scheduled this week');
    }

    private function tableQuery(): Builder
    {
        $start = $this->weekStartDate();
        $end = $this->weekEndDate();

        return EducationBooking::query()
            ->visibleToCurrentUser()
            ->whereHas('dayPeriods', fn ($query) => $query->whereBetween('date', [$start->toDateString(), $end->toDateString()]))
            ->with([
                'dayPeriods' => fn ($query) => $query->whereBetween('date', [$start->toDateString(), $end->toDateString()]),
                'education_client' => fn ($query) => $query->withTrashed(),
                'candidate' => fn ($query) => $query->withTrashed(),
                'jobTitle',
            ]);
    }

    private function clientLabel(EducationBooking $record): string
    {
        $client = $record->education_client;

        if (! $client) {
            return 'Unknown client';
        }

        return $client->trashed() ? "{$client->name} (deleted)" : $client->name;
    }

    private function candidateLabel(EducationBooking $record): string
    {
        $candidate = $record->candidate;

        if (! $candidate) {
            return 'Unknown candidate';
        }

        $name = trim("{$candidate->first_name} {$candidate->last_name}");

        return $candidate->trashed() ? "{$name} (deleted)" : $name;
    }

    /** @return array<IconColumn> */
    private function dayColumns(): array
    {
        return collect(range(0, 6))
            ->map(function (int $offset) {
                return IconColumn::make("day_{$offset}")
                    ->label(fn (): string => $this->weekStartDate()->copy()->addDays($offset)->format('D'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(function (EducationBooking $record) use ($offset): bool {
                        $date = $this->weekStartDate()->copy()->addDays($offset);

                        return $record->dayPeriods->contains(
                            fn (EducationBookingDayPeriod $dayPeriod): bool => $dayPeriod->date->isSameDay($date)
                        );
                    })
                    ->url(function (EducationBooking $record, bool $state) use ($offset): ?string {
                        if ($state) {
                            return null;
                        }

                        if ($this->hasPreviousDayBooking($record, $offset)) {
                            return EducationBookingResource::getUrl('edit', ['record' => $record]);
                        }

                        $date = $this->weekStartDate()->copy()->addDays($offset);

                        return EducationBookingResource::getUrl('create', [
                            'candidate_id' => $record->candidate_id,
                            'education_client_id' => $record->education_client_id,
                            'job_title_id' => $record->job_title_id,
                            'start_date' => $date->toDateString(),
                        ]);
                    });
            })
            ->all();
    }

    private function hasPreviousDayBooking(EducationBooking $record, int $offset): bool
    {
        if ($offset === 0) {
            return false;
        }

        $previousDate = $this->weekStartDate()->copy()->addDays($offset - 1);

        return $record->dayPeriods->contains(
            fn (EducationBookingDayPeriod $dayPeriod): bool => $dayPeriod->date->isSameDay($previousDate)
        );
    }
}
