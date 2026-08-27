<?php

namespace App\Filament\Resources\ComplianceItemJobTitles;

use App\Filament\Resources\ComplianceItemJobTitles\Pages\ListComplianceItemJobTitles;
use App\Filament\Resources\ComplianceItemJobTitles\Tables\ComplianceItemJobTitlesTable;
use App\Models\ComplianceItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ComplianceItemJobTitleResource extends Resource
{
    protected static ?string $model = ComplianceItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Required Job Titles';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Required Job Titles';

    protected static ?string $modelLabel = 'Required Job Title';

    public static function canViewAny(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public static function table(Table $table): Table
    {
        return ComplianceItemJobTitlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplianceItemJobTitles::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id());
    }
}
