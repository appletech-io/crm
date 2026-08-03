<?php

namespace App\Filament\Resources\Vacancies\Schemas;

use App\Filament\Widgets\VacancyActivityTimeline;
use App\Models\Client;
use App\Models\JobStatus;
use App\Models\JobTitle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
                                            ->required()
                                            ->searchable()
                                            ->preload(),

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
                                            ->prefix('£'),

                                        TextInput::make('salary_max')
                                            ->label('Salary (Max)')
                                            ->numeric()
                                            ->prefix('£'),

                                        TextInput::make('positions_available')
                                            ->label('Positions Available')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required(),

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
                                    ]),
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
