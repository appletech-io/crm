<?php

namespace App\Filament\Resources\ReferenceForms;

use App\Filament\Resources\ReferenceForms\Pages\CreateReferenceForm;
use App\Filament\Resources\ReferenceForms\Pages\EditReferenceForm;
use App\Filament\Resources\ReferenceForms\Pages\ListReferenceForms;
use App\Filament\Resources\ReferenceForms\Schemas\ReferenceFormForm;
use App\Filament\Resources\ReferenceForms\Tables\ReferenceFormsTable;
use App\Models\ReferenceForm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReferenceFormResource extends Resource
{
    protected static ?string $model = ReferenceForm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Reference Forms';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Reference Forms';

    protected static ?string $modelLabel = 'Reference Form';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'site_admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ReferenceFormForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferenceFormsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferenceForms::route('/'),
            'create' => CreateReferenceForm::route('/create'),
            'edit' => EditReferenceForm::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id());
    }
}
