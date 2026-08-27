<?php

namespace App\Filament\Resources\Candidates;

use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Filament\Resources\Candidates\Schemas\CandidateForm;
use App\Filament\Resources\Candidates\Tables\CandidatesTable;
use App\Models\Candidate;
use App\Models\Industry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The generic-candidate equivalent of EducationCandidateResource/
 * HealthcareCandidateResource's "Candidates" nav item — a single tabbed
 * edit form (see CandidateForm) covering Personal Details, Availability &
 * Skills, Pay Rates, Employment History, Documents, Formatted CV, Weekly
 * Availability, Activity, and Compliance, reusing the same generic
 * widgets/jobs Education and Healthcare already share.
 */
class CandidateResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'first_name';

    protected static ?string $navigationLabel = 'Candidates';

    protected static ?string $pluralModelLabel = 'Candidates';

    protected static ?string $modelLabel = 'Candidate';

    public static function canViewAny(): bool
    {
        return Industry::candidateModelForSlug(active_industry() ?? '') === Candidate::class;
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'phone', 'mobile', 'email'];
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
        return CandidateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidates::route('/'),
            'create' => CreateCandidate::route('/create'),
            'edit' => EditCandidate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('industry_id', active_industry_id())
            ->visibleToCurrentUser();
    }
}
