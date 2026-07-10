<?php

namespace App\Filament\Resources\Vetting\Tables;

use App\Filament\Resources\Vetting\VettingResource;
use App\Models\EducationCandidate;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VettingTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('statuses.status'))
            ->columns([
                TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('candidate_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (EducationCandidate $record): string => $record->currentStatusName() ?? 'No Status')
                    ->color(fn (EducationCandidate $record): string => $record->statuses->first()?->status?->color ?? 'gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->url(fn (EducationCandidate $record): string => VettingResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('created_at');
    }
}
