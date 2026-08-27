<?php

namespace App\Filament\Resources\Candidates\Tables;

use App\Models\Candidate;
use App\Services\Candidates\ComplianceRequirements;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['jobTitle', 'consultant']))
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
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('jobTitle.name')
                    ->label('Job Title')
                    ->placeholder('—'),
                TextColumn::make('consultant.name')
                    ->label('Consultant')
                    ->placeholder('—'),
                TextColumn::make('average_rating')
                    ->label('Rating')
                    ->badge()
                    // A plain formatStateUsing() never actually runs when the
                    // raw average_rating is null — Filament's TextColumn
                    // treats a blank state as "nothing to render" and skips
                    // straight past it, badge and all. Using ->state() to
                    // always produce a non-blank string (same trick the
                    // Compliance column below already relies on) sidesteps
                    // that entirely.
                    ->state(fn (Candidate $record): string => $record->average_rating !== null
                        ? number_format($record->average_rating, 1)." ★ ({$record->ratings_count})"
                        : 'Not yet rated')
                    ->color(fn (Candidate $record): string => match (true) {
                        $record->average_rating === null => 'gray',
                        $record->average_rating >= 4 => 'success',
                        $record->average_rating >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('compliance')
                    ->label('Compliance')
                    ->badge()
                    ->state(function (Candidate $record): string {
                        $checks = ComplianceRequirements::forJobTitle($record, $record->jobTitle);
                        $complete = collect($checks)->filter(fn (array $check): bool => $check['complete'])->count();

                        return "{$complete} / ".count($checks).' complete';
                    })
                    ->color(fn (Candidate $record): string => ComplianceRequirements::isCompleteForJobTitle($record, $record->jobTitle) ? 'success' : 'warning'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
