<?php

namespace App\Filament\Resources\ClientContactJobTitles;

use App\Filament\Resources\ClientContactJobTitles\Pages\CreateClientContactJobTitle;
use App\Filament\Resources\ClientContactJobTitles\Pages\EditClientContactJobTitle;
use App\Filament\Resources\ClientContactJobTitles\Pages\ListClientContactJobTitles;
use App\Filament\Resources\ClientContactJobTitles\Schemas\ClientContactJobTitleForm;
use App\Filament\Resources\ClientContactJobTitles\Tables\ClientContactJobTitlesTable;
use App\Models\ClientContactJobTitle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ClientContactJobTitleResource extends Resource
{
    protected static ?string $model = ClientContactJobTitle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Contact Job Titles';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Contact Job Titles';

    protected static ?string $modelLabel = 'Contact Job Title';

    public static function canViewAny(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return ClientContactJobTitleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientContactJobTitlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientContactJobTitles::route('/'),
            'create' => CreateClientContactJobTitle::route('/create'),
            'edit' => EditClientContactJobTitle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id());
    }
}
