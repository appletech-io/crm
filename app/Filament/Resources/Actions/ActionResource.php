<?php

namespace App\Filament\Resources\Actions;

use App\Filament\Resources\Actions\Pages\CreateAction;
use App\Filament\Resources\Actions\Pages\EditAction;
use App\Filament\Resources\Actions\Pages\ListActions;
use App\Filament\Resources\Actions\Schemas\ActionForm;
use App\Filament\Resources\Actions\Tables\ActionsTable;
use App\Models\Action;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActionResource extends Resource
{
    protected static ?string $model = Action::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static \UnitEnum|string|null $navigationGroup = 'Admin';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Actions';

    protected static ?string $pluralModelLabel = 'Actions';

    protected static ?string $modelLabel = 'Action';

    public static function canViewAny(): bool
    {
        return active_industry() !== null
            && auth()->user()?->hasAnyRole(['admin', 'site_admin', 'consultant', 'compliance']);
    }

    /**
     * Only admins configure automations — consultants and compliance can see
     * this list to understand what's notifying them, but not change it.
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'site_admin']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'site_admin']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'site_admin']) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'site_admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ActionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
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
            'index' => ListActions::route('/'),
            'create' => CreateAction::route('/create'),
            'edit' => EditAction::route('/{record}/edit'),
        ];
    }
}
