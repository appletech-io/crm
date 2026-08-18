<?php

namespace App\Filament\Resources\QualificationJobTitles;

use App\Filament\Resources\QualificationJobTitles\Pages\ListQualificationJobTitles;
use App\Filament\Resources\QualificationJobTitles\Tables\QualificationJobTitlesTable;
use App\Models\Qualification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class QualificationJobTitleResource extends Resource
{
    protected static ?string $model = Qualification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Allowed Job Titles';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Allowed Job Titles';

    protected static ?string $modelLabel = 'Allowed Job Title';

    public static function canViewAny(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public static function table(Table $table): Table
    {
        return QualificationJobTitlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQualificationJobTitles::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id());
    }
}
