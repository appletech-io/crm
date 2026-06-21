<?php

namespace App\Filament\Widgets;

use App\Models\EducationBooking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentEducationBookings extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EducationBooking::query()->latest()->limit(5)
            )
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client'),
                TextColumn::make('candidate.name')
                    ->label('Candidate'),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->paginated(false);
    }
}
