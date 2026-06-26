<?php

namespace App\Filament\Resources\CandidateSkills;

use App\Filament\Resources\CandidateSkills\Pages\EditCandidateSkill;
use App\Filament\Resources\CandidateSkills\Pages\ListCandidateSkills;
use App\Filament\Resources\CandidateSkills\Schemas\CandidateSkillForm;
use App\Filament\Resources\CandidateSkills\Tables\CandidateSkillsTable;
use App\Models\CandidateSkill;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CandidateSkillResource extends Resource
{
    protected static ?string $model = CandidateSkill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Skills';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $pluralModelLabel = 'Skills';

    protected static ?string $modelLabel = 'Skill';

    public static function canViewAny(): bool
    {
        return active_industry() !== null && auth()->user()?->hasAnyRole(['admin', 'site_admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return CandidateSkillForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidateSkillsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidateSkills::route('/'),
            'edit' => EditCandidateSkill::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', Auth::user()->company_id)
            ->where('industry_id', active_industry_id())
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name');
    }
}
