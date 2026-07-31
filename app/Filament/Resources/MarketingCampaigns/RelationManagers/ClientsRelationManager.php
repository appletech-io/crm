<?php

namespace App\Filament\Resources\MarketingCampaigns\RelationManagers;

use App\Models\ClientPool;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Clients';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->inverseRelationship('campaigns')
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
                    ->modalHeading('Add Client to Campaign')
                    ->recordSelectSearchColumns(['name'])
                    ->multiple(),

                Action::make('addFromPool')
                    ->label('Add from Pool')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->schema([
                        Select::make('client_pool_id')
                            ->label('Client Pool')
                            ->searchable()
                            ->required()
                            ->options(fn (): array => ClientPool::query()
                                ->where('industry_id', active_industry_id())
                                ->where(fn (Builder $query) => $query
                                    ->where('user_id', Auth::id())
                                    ->orWhere(fn (Builder $q) => $q->where('company_pool', true)->whereNull('user_id'))
                                )
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray()),
                    ])
                    ->action(function (array $data): void {
                        $pool = ClientPool::find($data['client_pool_id']);

                        $this->getOwnerRecord()->clients()->syncWithoutDetaching($pool->clients->pluck('id'));

                        Notification::make()
                            ->success()
                            ->title("Added {$pool->clients->count()} client(s) from {$pool->name}")
                            ->send();
                    }),
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
