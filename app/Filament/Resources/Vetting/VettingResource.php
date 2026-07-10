<?php

namespace App\Filament\Resources\Vetting;

use App\Filament\Resources\Vetting\Pages\ListVetting;
use App\Filament\Resources\Vetting\Pages\VettingWizard;
use App\Filament\Resources\Vetting\Tables\VettingTable;
use App\Models\EducationCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VettingResource extends Resource
{
    protected static ?string $model = EducationCandidate::class;

    protected static ?string $slug = 'vetting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Compliance';

    protected static ?string $recordTitleAttribute = 'first_name';

    protected static ?string $pluralModelLabel = 'Compliance';

    protected static ?string $modelLabel = 'Candidate';

    public static function canViewAny(): bool
    {
        return active_industry() === 'education';
    }

    public static function table(Table $table): Table
    {
        return VettingTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVetting::route('/'),
            'edit' => VettingWizard::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('statuses.status', fn (Builder $query) => $query->where('name', 'Vetting'));
    }
}
