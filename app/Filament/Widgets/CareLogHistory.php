<?php

namespace App\Filament\Widgets;

use App\Enums\Healthcare\Wellbeing;
use App\Models\Booking;
use App\Models\CareLog;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only history of care logs a candidate has submitted for their
 * shifts — reused as-is for both the per-booking "Care Logs" tab
 * (BookingForm) and the per-candidate "Care Logs" tab
 * (HealthcareCandidateForm), the same way CandidateBookingsTable is shared
 * across candidate forms. $record decides the scope: a Booking narrows to
 * that booking's own shifts; any other model (a candidate) shows every
 * shift across all of their bookings.
 */
class CareLogHistory extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Care Logs')
            ->query(fn (): Builder => $this->careLogsQuery())
            ->columns([
                TextColumn::make('bookingDay.date')
                    ->label('Date')
                    ->date('D jS M Y'),
                TextColumn::make('bookingDay.booking.client.name')
                    ->label('Client')
                    ->placeholder('—'),
                TextColumn::make('bookingDay.period')
                    ->label('Session')
                    ->formatStateUsing(fn ($state): string => $state->label()),
                TextColumn::make('wellbeing')
                    ->label('Wellbeing')
                    ->badge()
                    ->formatStateUsing(fn (Wellbeing $state): string => $state->label())
                    ->color(fn (Wellbeing $state): string => $state->color()),
                IconColumn::make('incidents_occurred')
                    ->label('Issue Flagged')
                    ->boolean()
                    // IconColumn::boolean()'s defaults (true = success,
                    // false = danger) are backwards for this field — a
                    // flagged issue is the bad outcome, not the good one.
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
                IconColumn::make('medication_administered')
                    ->label('Medication')
                    ->boolean(),
                TextColumn::make('handover_notes')
                    ->label('Handover Notes')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('submitted_at')
                    ->label('Logged At')
                    ->dateTime('j M Y, g:ia')
                    ->placeholder('—'),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No care logs yet.');
    }

    private function careLogsQuery(): Builder
    {
        if (! $this->record) {
            return CareLog::query()->whereRaw('1 = 0');
        }

        return CareLog::query()
            ->when(
                $this->record instanceof Booking,
                fn (Builder $query): Builder => $query->whereHas(
                    'bookingDay',
                    fn ($query) => $query->where('booking_id', $this->record->id)
                ),
                fn (Builder $query): Builder => $query->whereHas(
                    'bookingDay.booking',
                    fn ($query) => $query
                        ->where('candidate_id', $this->record->id)
                        ->where('candidate_type', $this->record::class)
                )
            )
            ->with(['bookingDay.booking.client']);
    }
}
