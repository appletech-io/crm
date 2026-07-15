<?php

namespace App\Filament\Resources\Bookings\Widgets;

use App\Filament\Resources\Bookings\BookingFilters;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\BookingDay;
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
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('edit', ['record' => $record]))
            ->groups([
                Group::make('client_id')
                    ->label('Client')
                    ->getTitleFromRecordUsing(fn (Booking $record): string => $this->clientLabel($record))
                    ->collapsible(),
            ])
            ->defaultGroup('client_id')
            ->groupingSettingsHidden()
            ->filters([
                BookingFilters::client(),
                BookingFilters::candidate(),
                BookingFilters::consultant(),
            ])
            ->columns([
                TextColumn::make('candidate_name')
                    ->label('Candidate')
                    ->getStateUsing(fn (Booking $record): string => $this->candidateLabel($record)),
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

        return Booking::query()
            ->visibleToCurrentUser()
            ->whereHas('dayPeriods', fn ($query) => $query->whereBetween('date', [$start->toDateString(), $end->toDateString()]))
            ->with([
                'dayPeriods' => fn ($query) => $query->whereBetween('date', [$start->toDateString(), $end->toDateString()]),
                'client' => fn ($query) => $query->withTrashed(),
                'candidate' => fn ($query) => $query->withTrashed(),
                'jobTitle',
            ]);
    }

    private function clientLabel(Booking $record): string
    {
        $client = $record->client;

        if (! $client) {
            return 'Unknown client';
        }

        return $client->trashed() ? "{$client->name} (deleted)" : $client->name;
    }

    private function candidateLabel(Booking $record): string
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
                    ->getStateUsing(fn (Booking $record): string => $this->dayStatus($record, $offset))
                    ->icon(fn (Booking $record): string => match ($this->dayStatus($record, $offset)) {
                        'booked' => 'heroicon-o-check-circle',
                        'cancelled' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-minus',
                    })
                    ->color(fn (Booking $record): string => match ($this->dayStatus($record, $offset)) {
                        'booked' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->url(function (Booking $record, string $state) use ($offset): ?string {
                        if ($this->dayStatus($record, $offset) !== 'empty') {
                            return null;
                        }

                        if ($this->hasPreviousDayBooking($record, $offset)) {
                            return BookingResource::getUrl('edit', ['record' => $record]);
                        }

                        $date = $this->weekStartDate()->copy()->addDays($offset);

                        return BookingResource::getUrl('create', [
                            'candidate_id' => $record->candidate_id,
                            'client_id' => $record->client_id,
                            'job_title_id' => $record->job_title_id,
                            'start_date' => $date->toDateString(),
                        ]);
                    });
            })
            ->all();
    }

    private function dayStatus(Booking $record, int $offset): string
    {
        $dayPeriod = $this->dayPeriodFor($record, $offset);

        return match (true) {
            $dayPeriod === null => 'empty',
            $dayPeriod->isCancelled() => 'cancelled',
            default => 'booked',
        };
    }

    private function dayPeriodFor(Booking $record, int $offset): ?BookingDay
    {
        $date = $this->weekStartDate()->copy()->addDays($offset);

        return $record->dayPeriods->first(
            fn (BookingDay $dayPeriod): bool => $dayPeriod->date->isSameDay($date)
        );
    }

    private function hasPreviousDayBooking(Booking $record, int $offset): bool
    {
        if ($offset === 0) {
            return false;
        }

        return $this->dayPeriodFor($record, $offset - 1) !== null;
    }
}
