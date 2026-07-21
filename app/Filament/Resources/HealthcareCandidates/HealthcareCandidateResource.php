<?php

namespace App\Filament\Resources\HealthcareCandidates;

use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Filament\Resources\HealthcareCandidates\Schemas\HealthcareCandidateForm;
use App\Filament\Resources\HealthcareCandidates\Tables\HealthcareCandidatesTable;
use App\Models\HealthcareCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HealthcareCandidateResource extends Resource
{
    protected static ?string $model = HealthcareCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'first_name';

    protected static ?string $navigationLabel = 'Candidates';

    protected static ?string $pluralModelLabel = 'Candidates';

    protected static ?string $modelLabel = 'HealthcareCandidate';

    public static function canViewAny(): bool
    {
        return active_industry() === 'healthcare';
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
        return HealthcareCandidateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HealthcareCandidatesTable::configure($table);
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
            'index' => ListHealthcareCandidates::route('/'),
            'edit' => EditHealthcareCandidate::route('/{record}/edit'),
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
