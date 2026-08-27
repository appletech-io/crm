<?php

namespace App\Filament\Resources\CandidateCompliance;

use App\Filament\Resources\CandidateCompliance\Pages\EditCandidateCompliance;
use App\Filament\Resources\CandidateCompliance\Pages\ListCandidateCompliance;
use App\Filament\Resources\CandidateCompliance\Tables\CandidateComplianceTable;
use App\Models\Candidate;
use App\Models\Industry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A quick-access nav item listing candidates who still have outstanding
 * Compliance Items — for actually editing compliance, see CandidateForm's
 * "Compliance" tab on the main Candidates resource (CandidateComplianceForm
 * is shared by both, plus the candidate's own self-service portal page).
 * Only ever visible for an industry whose candidate model resolves to
 * Candidate::class, so it never appears alongside Education/Healthcare's
 * own "Compliance" nav item.
 */
class CandidateComplianceResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static ?string $slug = 'candidate-compliance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Compliance';

    protected static ?string $recordTitleAttribute = 'first_name';

    protected static ?string $pluralModelLabel = 'Compliance';

    protected static ?string $modelLabel = 'Candidate';

    public static function canViewAny(): bool
    {
        return Industry::candidateModelForSlug(active_industry() ?? '') === Candidate::class;
    }

    public static function table(Table $table): Table
    {
        return CandidateComplianceTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidateCompliance::route('/'),
            'edit' => EditCandidateCompliance::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('industry_id', active_industry_id())
            ->visibleToCurrentUser();
    }
}
