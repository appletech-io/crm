<?php

namespace App\Filament\Resources\SampleProfiles;

use App\Filament\Resources\SampleProfiles\Pages\CreateSampleProfile;
use App\Filament\Resources\SampleProfiles\Pages\ListSampleProfiles;
use App\Filament\Resources\SampleProfiles\Schemas\SampleProfileForm;
use App\Filament\Resources\SampleProfiles\Tables\SampleProfilesTable;
use App\Models\SampleProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SampleProfileResource extends Resource
{
    public const int MAX_PER_SECTOR = 5;

    protected static ?string $model = SampleProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Sample Profiles';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Sample Profiles';

    protected static ?string $modelLabel = 'Sample Profile';

    public static function canViewAny(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return SampleProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SampleProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSampleProfiles::route('/'),
            'create' => CreateSampleProfile::route('/create'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id());
    }
}
