<?php

namespace App\Filament\Resources\ClientTypes;

use App\Filament\Resources\ClientTypes\Pages\CreateClientType;
use App\Filament\Resources\ClientTypes\Pages\EditClientType;
use App\Filament\Resources\ClientTypes\Pages\ListClientTypes;
use App\Filament\Resources\ClientTypes\Schemas\ClientTypeForm;
use App\Filament\Resources\ClientTypes\Tables\ClientTypesTable;
use App\Models\ClientType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ClientTypeResource extends Resource
{
    protected static ?string $model = ClientType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Client Types';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Client Types';

    protected static ?string $modelLabel = 'Client Type';

    public static function canViewAny(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return ClientTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientTypes::route('/'),
            'create' => CreateClientType::route('/create'),
            'edit' => EditClientType::route('/{record}/edit'),
        ];
    }
}
