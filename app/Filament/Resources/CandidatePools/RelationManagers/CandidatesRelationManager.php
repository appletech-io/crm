<?php

namespace App\Filament\Resources\CandidatePools\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CandidatesRelationManager extends RelationManager
{
    protected static string $relationship = 'candidates';

    protected static ?string $title = 'Candidates in this pool';

    protected static ?string $recordTitleAttribute = 'first_name';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn ($record): string => trim("{$record->first_name} {$record->last_name}"))
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('statuses.status.name')
                    ->label('Status')
                    ->badge()
                    ->separator(','),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add Candidate')
                    ->modalHeading('Add Candidate to Pool')
                    ->recordSelectSearchColumns(['first_name', 'last_name', 'email'])
                    ->multiple(),
            ])
            ->recordActions([
                DetachAction::make()->label('Remove'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->label('Remove selected'),
                ]),
            ]);
    }
}
