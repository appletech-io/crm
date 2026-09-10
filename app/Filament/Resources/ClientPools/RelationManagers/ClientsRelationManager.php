<?php

namespace App\Filament\Resources\ClientPools\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Clients in this pool';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->inverseRelationship('pools')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('clientType.name')
                    ->label('Client Type'),
                TextColumn::make('city')
                    ->searchable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add Client')
                    ->modalHeading('Add Client to Pool')
                    ->recordSelectSearchColumns(['name'])
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->visibleToCurrentUser())
                    ->multiple(),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remove'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove selected'),
                ]),
            ]);
    }
}
