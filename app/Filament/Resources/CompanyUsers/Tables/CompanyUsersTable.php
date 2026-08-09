<?php

namespace App\Filament\Resources\CompanyUsers\Tables;

use App\Filament\Resources\CompanyUsers\CompanyUserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CompanyUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('job_title')
                    ->label('Job Title'),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),
                TextColumn::make('complianceOfficer.name')
                    ->label('Compliance Officer')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('setComplianceOfficer')
                    ->label('Compliance Officer')
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->visible(fn (User $record): bool => $record->hasRole('consultant'))
                    ->schema(fn (User $record): array => [
                        Select::make('compliance_officer_id')
                            ->label('Compliance Officer')
                            ->options(fn (): array => self::eligibleComplianceOfficers($record)->pluck('name', 'id')->all())
                            ->searchable()
                            ->default($record->compliance_officer_id),
                    ])
                    ->modalHeading(fn (User $record): string => "Compliance officer for {$record->name}")
                    ->action(function (User $record, array $data): void {
                        $complianceOfficerId = $data['compliance_officer_id'] ?? null;

                        if ($complianceOfficerId !== null && ! self::eligibleComplianceOfficers($record)->contains('id', (int) $complianceOfficerId)) {
                            return;
                        }

                        $record->update(['compliance_officer_id' => $complianceOfficerId]);
                    })
                    ->successNotificationTitle('Compliance officer updated'),
                Action::make('manageRoles')
                    ->label('Manage Roles')
                    ->icon('heroicon-o-identification')
                    ->color('gray')
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('Roles')
                            ->options(array_combine(
                                CompanyUserResource::ASSIGNABLE_ROLES,
                                array_map('ucfirst', CompanyUserResource::ASSIGNABLE_ROLES),
                            ))
                            ->default(fn (User $record): array => $record->roles->pluck('name')
                                ->intersect(CompanyUserResource::ASSIGNABLE_ROLES)
                                ->values()
                                ->toArray()),
                    ])
                    ->modalHeading(fn (User $record): string => "Manage roles for {$record->name}")
                    ->action(function (User $record, array $data): void {
                        $retainedRoles = $record->roles->pluck('name')
                            ->diff(CompanyUserResource::ASSIGNABLE_ROLES)
                            ->values()
                            ->toArray();

                        $record->syncRoles([...$retainedRoles, ...$data['roles']]);
                    })
                    ->successNotificationTitle('Roles updated'),
            ]);
    }

    /** @return Collection<int, User> */
    private static function eligibleComplianceOfficers(User $record): Collection
    {
        return User::role('compliance')
            ->where('id', '!=', $record->id)
            ->get(['id', 'name']);
    }
}
