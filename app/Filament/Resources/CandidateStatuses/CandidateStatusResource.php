<?php

namespace App\Filament\Resources\CandidateStatuses;

use App\Filament\Resources\CandidateStatuses\Pages\EditCandidateStatus;
use App\Filament\Resources\CandidateStatuses\Pages\ListCandidateStatuses;
use App\Filament\Resources\CandidateStatuses\Pages\ManageCandidateStatusAutomations;
use App\Filament\Resources\CandidateStatuses\Schemas\CandidateStatusForm;
use App\Filament\Resources\CandidateStatuses\Tables\CandidateStatusesTable;
use App\Models\CandidateStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CandidateStatusResource extends Resource
{
    protected static ?string $model = CandidateStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Candidate Statuses';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Candidate Statuses';

    protected static ?string $modelLabel = 'Candidate Status';

    public static function canViewAny(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return CandidateStatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidateStatusesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidateStatuses::route('/'),
            'automations' => ManageCandidateStatusAutomations::route('/automations'),
            'edit' => EditCandidateStatus::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id());
    }
}
