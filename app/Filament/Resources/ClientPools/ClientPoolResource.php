<?php

namespace App\Filament\Resources\ClientPools;

use App\Filament\Resources\ClientPools\Pages\EditClientPool;
use App\Filament\Resources\ClientPools\Pages\ListClientPools;
use App\Filament\Resources\ClientPools\Schemas\ClientPoolForm;
use App\Filament\Resources\ClientPools\Tables\ClientPoolsTable;
use App\Models\ClientPool;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ClientPoolResource extends Resource
{
    protected static ?string $model = ClientPool::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralModelLabel = 'Client Pools';

    protected static ?string $modelLabel = 'Pool';

    public static function canViewAny(): bool
    {
        return active_industry() !== null;
    }

    public static function form(Schema $schema): Schema
    {
        return ClientPoolForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientPoolsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientPools::route('/'),
            'edit' => EditClientPool::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('industry_id', active_industry_id())
            ->where(fn (Builder $query) => $query
                ->where('user_id', Auth::id())
                ->orWhere(fn (Builder $q) => $q->where('company_pool', true)->whereNull('user_id'))
            );
    }
}
