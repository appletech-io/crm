<?php

namespace App\Filament\Resources\CompanyUsers;

use App\Filament\Resources\CompanyUsers\Pages\EditCompanyUser;
use App\Filament\Resources\CompanyUsers\Pages\ListCompanyUsers;
use App\Filament\Resources\CompanyUsers\Schemas\CompanyUserForm;
use App\Filament\Resources\CompanyUsers\Tables\CompanyUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompanyUserResource extends Resource
{
    /**
     * Roles an 'admin' is allowed to assign or remove here. Deliberately excludes
     * 'admin' and 'site_admin' so a company admin can't escalate themselves or
     * anyone else to a more privileged role from this screen.
     *
     * @var array<int, string>
     */
    public const array ASSIGNABLE_ROLES = ['consultant', 'resourcer', 'compliance'];

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Team & Roles';

    protected static ?string $pluralModelLabel = 'Team';

    protected static ?string $modelLabel = 'Team Member';

    public static function getNavigationGroup(): ?string
    {
        return 'Admin';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return CompanyUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanyUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Scoped to the current company by the model's own global scope. Excludes
     * candidate/client portal logins, which are managed separately under
     * "Candidate & Client Logins" and don't have staff roles to assign.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('candidate_id')
            ->whereNull('client_contact_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanyUsers::route('/'),
            'edit' => EditCompanyUser::route('/{record}/edit'),
        ];
    }
}
