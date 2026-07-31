<?php

namespace App\Filament\Resources\ClientPools\RelationManagers;

use App\Models\ClientPool;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

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
                    ->multiple()
                    ->visible(fn (): bool => $this->canManageMembership()),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remove')
                    ->visible(fn (): bool => $this->canManageMembership()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove selected')
                        ->visible(fn (): bool => $this->canManageMembership()),
                ]),
            ]);
    }

    /**
     * Anyone can manage their own personal pool's members (only its owner
     * can even open it — see ClientPoolResource::getEloquentQuery()), but
     * only admins may add/remove clients on a company-wide pool, since
     * every consultant can see and open those.
     */
    private function canManageMembership(): bool
    {
        /** @var ClientPool $pool */
        $pool = $this->getOwnerRecord();

        if (! $pool->company_pool) {
            return true;
        }

        return Auth::user()?->hasAnyRole(['admin', 'site_admin']) ?? false;
    }
}
