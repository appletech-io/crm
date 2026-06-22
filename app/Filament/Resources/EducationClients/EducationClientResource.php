<?php

namespace App\Filament\Resources\EducationClients;

use App\Filament\Resources\EducationClients\Pages\CreateEducationClient;
use App\Filament\Resources\EducationClients\Pages\EditEducationClient;
use App\Filament\Resources\EducationClients\Pages\ListEducationClients;
use App\Filament\Resources\EducationClients\Schemas\EducationClientForm;
use App\Filament\Resources\EducationClients\Tables\EducationClientsTable;
use App\Models\EducationClient;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EducationClientResource extends Resource
{
    protected static ?string $model = EducationClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Clients';

    protected static ?string $pluralModelLabel = 'Clients';

    protected static ?string $modelLabel = 'Client';

    public static function canViewAny(): bool
    {
        return active_industry() === 'education';
    }

    public static function form(Schema $schema): Schema
    {
        return EducationClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EducationClientsTable::configure($table);
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
            'index' => ListEducationClients::route('/'),
            'create' => CreateEducationClient::route('/create'),
            'edit' => EditEducationClient::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
