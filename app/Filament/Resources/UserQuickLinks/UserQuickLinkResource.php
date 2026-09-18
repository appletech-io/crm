<?php

namespace App\Filament\Resources\UserQuickLinks;

use App\Filament\Resources\UserQuickLinks\Pages\CreateUserQuickLink;
use App\Filament\Resources\UserQuickLinks\Pages\EditUserQuickLink;
use App\Filament\Resources\UserQuickLinks\Pages\ListUserQuickLinks;
use App\Filament\Resources\UserQuickLinks\Schemas\UserQuickLinkForm;
use App\Filament\Resources\UserQuickLinks\Tables\UserQuickLinksTable;
use App\Models\UserQuickLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserQuickLinkResource extends Resource
{
    public const MAX_QUICK_LINKS = 6;

    protected static ?string $model = UserQuickLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmarkSquare;

    protected static ?string $navigationLabel = 'My Quick Links';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $pluralModelLabel = 'Quick Links';

    protected static ?string $modelLabel = 'Quick Link';

    public static function canViewAny(): bool
    {
        return ! (auth()->user()?->hasRole('site_admin') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return UserQuickLinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserQuickLinksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserQuickLinks::route('/'),
            'create' => CreateUserQuickLink::route('/create'),
            'edit' => EditUserQuickLink::route('/{record}/edit'),
        ];
    }
}
