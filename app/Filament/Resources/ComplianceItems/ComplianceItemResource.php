<?php

namespace App\Filament\Resources\ComplianceItems;

use App\Filament\Resources\ComplianceItems\Pages\CreateComplianceItem;
use App\Filament\Resources\ComplianceItems\Pages\EditComplianceItem;
use App\Filament\Resources\ComplianceItems\Pages\ListComplianceItems;
use App\Filament\Resources\ComplianceItems\Schemas\ComplianceItemForm;
use App\Filament\Resources\ComplianceItems\Tables\ComplianceItemsTable;
use App\Models\ComplianceItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ComplianceItemResource extends Resource
{
    protected static ?string $model = ComplianceItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Compliance Items';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Compliance Items';

    protected static ?string $modelLabel = 'Compliance Item';

    public static function canViewAny(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return ComplianceItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComplianceItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplianceItems::route('/'),
            'create' => CreateComplianceItem::route('/create'),
            'edit' => EditComplianceItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id());
    }
}
