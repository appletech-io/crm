<?php

namespace App\Filament\Resources\HealthcareVetting;

use App\Filament\Resources\HealthcareVetting\Pages\HealthcareVettingWizard;
use App\Filament\Resources\HealthcareVetting\Pages\ListHealthcareVetting;
use App\Filament\Resources\HealthcareVetting\Tables\HealthcareVettingTable;
use App\Models\HealthcareCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HealthcareVettingResource extends Resource
{
    protected static ?string $model = HealthcareCandidate::class;

    protected static ?string $slug = 'healthcare-vetting';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Compliance';

    protected static ?string $recordTitleAttribute = 'first_name';

    protected static ?string $pluralModelLabel = 'Compliance';

    protected static ?string $modelLabel = 'HealthcareCandidate';

    public static function canViewAny(): bool
    {
        return active_industry() === 'healthcare';
    }

    public static function table(Table $table): Table
    {
        return HealthcareVettingTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHealthcareVetting::route('/'),
            'edit' => HealthcareVettingWizard::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('statuses.status', fn (Builder $query) => $query->where('name', 'Vetting'));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
