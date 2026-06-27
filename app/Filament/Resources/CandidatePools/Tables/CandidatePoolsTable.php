<?php

namespace App\Filament\Resources\CandidatePools\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CandidatePoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('company_pool')
                    ->label('Company Pool')
                    ->boolean()
                    ->trueIcon('heroicon-o-building-office')
                    ->falseIcon('')
                    ->trueColor('primary')
                    ->tooltip(fn ($state): ?string => $state ? 'Visible to all consultants' : null),
                TextColumn::make('candidates_count')
                    ->label('Candidates')
                    ->counts('candidates')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()->label('Manage'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
