<?php

namespace App\Filament\Resources\CandidatePools;

use App\Filament\Resources\CandidatePools\Pages\EditCandidatePool;
use App\Filament\Resources\CandidatePools\Pages\ListCandidatePools;
use App\Filament\Resources\CandidatePools\Schemas\CandidatePoolForm;
use App\Filament\Resources\CandidatePools\Tables\CandidatePoolsTable;
use App\Models\CandidatePool;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CandidatePoolResource extends Resource
{
    protected static ?string $model = CandidatePool::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'My Pools';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralModelLabel = 'Candidate Pools';

    protected static ?string $modelLabel = 'Pool';

    public static function canViewAny(): bool
    {
        return active_industry() !== null;
    }

    public static function form(Schema $schema): Schema
    {
        return CandidatePoolForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidatePoolsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidatePools::route('/'),
            'edit' => EditCandidatePool::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('industry_id', active_industry_id())
            ->where(fn (Builder $query) => $query
                ->where('user_id', Auth::id())
                ->orWhere(fn (Builder $q) => $q->where('company_pool', true)->whereNull('user_id'))
            );
    }
}
