<?php

namespace App\Filament\Resources\EducationCandidates;

use App\Filament\Resources\EducationCandidates\Pages\BulkUploadCvs;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\EducationCandidates\Schemas\EducationCandidateForm;
use App\Filament\Resources\EducationCandidates\Tables\EducationCandidatesTable;
use App\Models\EducationCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EducationCandidateResource extends Resource
{
    protected static ?string $model = EducationCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'first_name';

    protected static ?string $navigationLabel = 'Candidates';

    protected static ?string $pluralModelLabel = 'Candidates';

    protected static ?string $modelLabel = 'EducationCandidate';

    public static function canViewAny(): bool
    {
        return active_industry() === 'education';
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'phone', 'mobile', 'email'];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'Phone' => $record->phone,
            'Mobile' => $record->mobile,
            'Email' => $record->email,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return EducationCandidateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EducationCandidatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleToCurrentUser();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEducationCandidates::route('/'),
            'bulk-upload-cvs' => BulkUploadCvs::route('/bulk-upload-cvs'),
            'edit' => EditEducationCandidate::route('/{record}/edit'),
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
