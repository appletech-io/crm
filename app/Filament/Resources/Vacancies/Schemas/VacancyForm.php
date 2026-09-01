<?php

namespace App\Filament\Resources\Vacancies\Schemas;

use App\Enums\VacancyEmploymentType;
use App\Enums\VacancyLocation;
use App\Filament\Widgets\VacancyActivityTimeline;
use App\Filament\Widgets\VacancyApplicantsTable;
use App\Filament\Widgets\VacancyMatchesTable;
use App\Filament\Widgets\VacancyPlacementsTable;
use App\Models\Client;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\Vacancy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class VacancyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('Details')
                            ->schema([
                                Section::make('Vacancy Details')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('client_id')
                                            ->label('Client')
                                            ->options(fn (): array => Client::query()
                                                ->where('industry_id', active_industry_id())
                                                ->pluck('name', 'id')
                                                ->toArray()
                                            )
                                            ->required(fn (Get $get): bool => $get('employment_type') !== VacancyEmploymentType::Temp->value)
                                            ->helperText('Optional for a temp vacancy covering general availability rather than a specific client.')
                                            ->searchable()
                                            ->live()
                                            ->preload(),

                                        Select::make('location')
                                            ->label('Location')
                                            ->options(VacancyLocation::options())
                                            ->helperText('City/town for this vacancy, used on the public jobs feed since there\'s no client to take it from.')
                                            ->searchable()
                                            ->visible(fn (Get $get): bool => blank($get('client_id'))),

                                        Select::make('job_title_id')
                                            ->label('Job Title')
                                            ->options(fn (): array => JobTitle::query()
                                                ->where('company_id', Auth::user()->company_id)
                                                ->where('industry_id', active_industry_id())
                                                ->pluck('name', 'id')
                                                ->toArray()
                                            )
                                            ->required()
                                            ->searchable()
                                            ->preload(),

                                        TextInput::make('title')
                                            ->label('Vacancy Title')
                                            ->helperText('The specific posting name, e.g. "Year 3 Class Teacher – September Start".')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(4)
                                            ->columnSpanFull(),

                                        Select::make('skills')
                                            ->label('Skills')
                                            ->relationship(
                                                name: 'skills',
                                                titleAttribute: 'name',
                                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                                    ->where('company_id', Auth::user()->company_id)
                                                    ->where('industry_id', active_industry_id())
                                                    ->orderBy('name'),
                                            )
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull(),

                                        TextInput::make('salary_min')
                                            ->label('Salary (Min)')
                                            ->numeric()
                                            ->prefix('£')
                                            ->visible(fn (Get $get): bool => $get('employment_type') !== VacancyEmploymentType::Temp->value),

                                        TextInput::make('salary_max')
                                            ->label('Salary (Max)')
                                            ->numeric()
                                            ->prefix('£')
                                            ->visible(fn (Get $get): bool => $get('employment_type') !== VacancyEmploymentType::Temp->value),

                                        TextInput::make('day_rate_min')
                                            ->label('Day Rate (Min)')
                                            ->numeric()
                                            ->prefix('£')
                                            ->visible(fn (Get $get): bool => $get('employment_type') === VacancyEmploymentType::Temp->value),

                                        TextInput::make('day_rate_max')
                                            ->label('Day Rate (Max)')
                                            ->numeric()
                                            ->prefix('£')
                                            ->visible(fn (Get $get): bool => $get('employment_type') === VacancyEmploymentType::Temp->value),

                                        TextInput::make('positions_available')
                                            ->label('Positions Available')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required(),

                                        Select::make('employment_type')
                                            ->label('Employment Type')
                                            ->options(VacancyEmploymentType::options())
                                            ->default(VacancyEmploymentType::Permanent->value)
                                            ->helperText('Temp roles are filled via a Booking and excluded from placement fee/margin reporting on this job.')
                                            ->native(false)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                // Clear whichever pay fields no longer apply so a
                                                // leftover salary/day rate from before a type switch
                                                // can't linger and confuse reporting for the new type.
                                                if ($state === VacancyEmploymentType::Temp->value) {
                                                    $set('salary_min', null);
                                                    $set('salary_max', null);
                                                } else {
                                                    $set('day_rate_min', null);
                                                    $set('day_rate_max', null);
                                                }
                                            })
                                            ->required(),

                                        TextInput::make('placement_fee_percentage')
                                            ->label('Placement Fee %')
                                            ->helperText('Used to estimate this job\'s value on the client\'s Pipeline tab. Not used for temp roles.')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.5)
                                            ->suffix('%')
                                            ->visible(fn (Get $get): bool => $get('employment_type') !== VacancyEmploymentType::Temp->value),

                                        DatePicker::make('start_date')
                                            ->label('Cover Needed From')
                                            ->helperText('Used to prefill the Booking when a shortlisted candidate is booked for this cover.')
                                            ->native(false)
                                            ->visible(fn (Get $get): bool => $get('employment_type') === VacancyEmploymentType::Temp->value),

                                        DatePicker::make('end_date')
                                            ->label('Cover Needed To')
                                            ->native(false)
                                            ->afterOrEqual('start_date')
                                            ->visible(fn (Get $get): bool => $get('employment_type') === VacancyEmploymentType::Temp->value),

                                        Select::make('job_status_id')
                                            ->label('Status')
                                            ->options(fn (): array => JobStatus::query()
                                                ->where('company_id', Auth::user()->company_id)
                                                ->where('industry_id', active_industry_id())
                                                ->pluck('name', 'id')
                                                ->toArray()
                                            )
                                            ->required()
                                            ->searchable(),

                                        Toggle::make('open_for_applications')
                                            ->label('Accepts Applications')
                                            ->helperText('Whether the public application link below is currently open. Independent of the Status field above.')
                                            ->default(true),

                                        DatePicker::make('listing_expires_at')
                                            ->label('Listing Expiry')
                                            ->helperText('When this vacancy should stop appearing on the public jobs feed. Leave blank to keep it listed indefinitely.')
                                            ->native(false),

                                        TextInput::make('apply_url')
                                            ->label('Application Link')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->copyable()
                                            ->formatStateUsing(fn (?Vacancy $record): ?string => $record ? route('vacancy.apply', $record->slug) : null)
                                            ->visible(fn (?Vacancy $record): bool => $record !== null)
                                            ->columnSpanFull(),

                                        Toggle::make('run_match')
                                            ->label('Match against all candidates now')
                                            ->helperText('Runs in the background and scores every candidate in your pool against this vacancy — check the Matches tab shortly after saving.')
                                            ->default(true)
                                            ->dehydrated(false)
                                            ->visible(fn (?Vacancy $record): bool => $record === null)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('Applicants')
                            ->schema([
                                LivewireComponent::make(VacancyApplicantsTable::class)
                                    ->key('vacancy-applicants-table')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Shortlisted')
                            ->schema([
                                LivewireComponent::make(VacancyApplicantsTable::class, ['onlyShortlisted' => true])
                                    ->key('vacancy-shortlisted-table')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Matches')
                            ->schema([
                                LivewireComponent::make(VacancyMatchesTable::class)
                                    ->key('vacancy-matches-table')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Placements')
                            ->schema([
                                LivewireComponent::make(VacancyPlacementsTable::class)
                                    ->key('vacancy-placements-table')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),

                        Tab::make('Activity')
                            ->schema([
                                LivewireComponent::make(VacancyActivityTimeline::class)
                                    ->key('vacancy-activity-timeline')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),
                    ]),
            ]);
    }
}
