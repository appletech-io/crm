<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

/**
 * Every booking this candidate has worked, across sectors — reused as-is
 * across the generic/Education/Healthcare candidate forms since Booking's
 * candidate relation is already polymorphic.
 */
class CandidateBookingsTable extends TableWidget
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
            ->heading('Bookings')
            ->query(fn () => $this->record?->bookings()->with(['client', 'company'])->latest('start_date') ?? Booking::query()->whereRaw('1 = 0'))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client'),

                TextColumn::make('start_date')
                    ->label('From')
                    ->date(),

                TextColumn::make('end_date')
                    ->label('To')
                    ->date(),

                TextColumn::make('company.name')
                    ->label('Agency'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No bookings');
    }
}
