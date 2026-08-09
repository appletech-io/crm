<?php

namespace App\Filament\Resources\PortalAccounts;

use App\Filament\Resources\PortalAccounts\Pages\ListPortalAccounts;
use App\Filament\Resources\PortalAccounts\Tables\PortalAccountsTable;
use App\Models\Industry;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PortalAccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Candidate & Client Logins';

    protected static ?string $pluralModelLabel = 'Portal Accounts';

    protected static ?string $modelLabel = 'Portal Account';

    public static function getNavigationGroup(): ?string
    {
        return 'Admin';
    }

    public static function canViewAny(): bool
    {
        return active_industry() !== null && (auth()->user()?->hasRole('admin') ?? false);
    }

    public static function table(Table $table): Table
    {
        return PortalAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Scoped to the current company (global scope) and active sector. Candidate
     * logins are matched via the morph type for the active sector's candidate
     * model; client logins via their linked client's industry.
     */
    public static function getEloquentQuery(): Builder
    {
        $candidateModel = Industry::candidateModelForSlug((string) active_industry());

        return parent::getEloquentQuery()
            ->where(function (Builder $query) use ($candidateModel): void {
                $query
                    ->when(
                        $candidateModel,
                        fn (Builder $q) => $q->where('candidate_type', $candidateModel),
                    )
                    ->orWhereHas(
                        'clientContact.client',
                        fn (Builder $q) => $q->where('industry_id', active_industry_id()),
                    );
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalAccounts::route('/'),
        ];
    }
}
