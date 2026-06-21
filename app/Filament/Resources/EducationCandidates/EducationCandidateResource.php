<?php

namespace App\Filament\Resources\EducationCandidates;

use App\Filament\Resources\EducationCandidates\Pages\CreateEducationCandidate;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\EducationCandidates\Schemas\EducationCandidateForm;
use App\Filament\Resources\EducationCandidates\Tables\EducationCandidatesTable;
use App\Models\EducationCandidate;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EducationCandidateResource extends Resource
{
    protected static ?string $model = EducationCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Candidates';

    protected static ?string $pluralModelLabel = 'Candidates';

    protected static ?string $modelLabel = 'Candidate';

    public static function canViewAny(): bool
    {
        return active_industry() === 'education';
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
        return [
            // ApplicationRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEducationCandidates::route('/'),
            'create' => CreateEducationCandidate::route('/create'),
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
