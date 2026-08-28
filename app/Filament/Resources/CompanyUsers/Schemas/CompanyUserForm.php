<?php

namespace App\Filament\Resources\CompanyUsers\Schemas;

use App\Filament\Resources\CompanyUsers\CompanyUserResource;
use App\Models\Industry;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CompanyUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('Roles')
                            ->schema([
                                CheckboxList::make('roles')
                                    ->hiddenLabel()
                                    ->options(array_combine(
                                        CompanyUserResource::ASSIGNABLE_ROLES,
                                        array_map('ucfirst', CompanyUserResource::ASSIGNABLE_ROLES),
                                    ))
                                    // Not ->relationship() — an admin may only touch this
                                    // assignable subset, never the full roles list (which
                                    // could include 'admin'/'site_admin'). The raw submitted
                                    // state is read directly in EditCompanyUser instead,
                                    // where it's merged back in alongside whatever
                                    // non-assignable roles this user already had.
                                    ->dehydrated(false)
                                    // ->default() only applies when a form is filled with a
                                    // null state (i.e. creating), never on Edit — where the
                                    // record's real attributes are always passed to fill().
                                    // Since 'roles' isn't a database column, it must be
                                    // populated via afterStateHydrated() instead.
                                    ->afterStateHydrated(function (CheckboxList $component, ?User $record): void {
                                        $component->state($record
                                            ? $record->roles->pluck('name')
                                                ->intersect(CompanyUserResource::ASSIGNABLE_ROLES)
                                                ->values()
                                                ->toArray()
                                            : []);
                                    }),
                            ]),

                        Tab::make('Compliance Officer')
                            ->visible(fn (?User $record): bool => $record?->hasRole('consultant') ?? false)
                            ->schema([
                                Select::make('compliance_officer_id')
                                    ->label('Compliance Officer')
                                    ->options(fn (?User $record): array => self::eligibleComplianceOfficers($record)->pluck('name', 'id')->all())
                                    ->searchable(),
                            ]),

                        Tab::make('KPI Targets')
                            ->visible(fn (?User $record): bool => $record?->hasRole('consultant') ?? false)
                            ->schema([
                                Repeater::make('kpiTargets')
                                    ->relationship()
                                    ->hiddenLabel()
                                    ->schema([
                                        Select::make('industry_id')
                                            ->label('Sector')
                                            ->options(fn (): array => self::companyIndustriesQuery()->pluck('name', 'id')->toArray())
                                            ->required()
                                            ->distinct()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->searchable()
                                            ->columnSpanFull(),
                                        TextInput::make('gp_target')
                                            ->label('Gross Profit Target')
                                            ->numeric()
                                            ->prefix('£')
                                            ->minValue(0),
                                        TextInput::make('candidate_days_target')
                                            ->label('Candidate Days Target')
                                            ->numeric()
                                            ->minValue(0),
                                        TextInput::make('working_candidates_target')
                                            ->label('Working Candidates Target')
                                            ->numeric()
                                            ->minValue(0),
                                        TextInput::make('clients_booked_target')
                                            ->label('Clients Booked Target')
                                            ->numeric()
                                            ->minValue(0),
                                        TextInput::make('rebook_rate_target')
                                            ->label('Rebook Rate Target')
                                            ->numeric()
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->maxValue(100),
                                    ])
                                    ->columns(3)
                                    ->itemLabel(fn (?array $state): ?string => filled($state['industry_id'] ?? null)
                                        ? Industry::find($state['industry_id'])?->name
                                        : 'KPI Target'
                                    )
                                    ->collapsible()
                                    ->collapsed()
                                    ->default([])
                                    // One set of targets per sector at most — a consultant
                                    // can work Education and/or Healthcare, never more.
                                    ->maxItems(fn (): int => self::companySectorCount())
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /** @return Collection<int, User> */
    private static function eligibleComplianceOfficers(?User $record): Collection
    {
        return User::role('compliance')
            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
            ->get(['id', 'name']);
    }

    private static function companySectorCount(): int
    {
        return self::companyIndustriesQuery()->count();
    }

    private static function companyIndustriesQuery(): Builder
    {
        return Industry::query()
            ->whereIn('id', function ($sub) {
                $sub->select('industry_id')
                    ->from('company_industry')
                    ->where('company_id', Auth::user()->company_id);
            });
    }
}
