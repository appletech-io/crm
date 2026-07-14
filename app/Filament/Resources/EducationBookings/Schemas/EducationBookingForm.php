<?php

namespace App\Filament\Resources\EducationBookings\Schemas;

use App\Enums\BookingDayPeriod;
use App\Models\EducationCandidate;
use App\Models\EducationClient;
use App\Models\JobTitle;
use App\Models\PayRate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EducationBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Booking Details')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('education_client_id')
                            ->label('Client')
                            ->options(fn (): array => EducationClient::query()
                                ->pluck('name', 'id')
                                ->toArray()
                            )
                            ->getOptionLabelUsing(function (mixed $value): ?string {
                                $client = EducationClient::withTrashed()->find($value);

                                if (! $client) {
                                    return null;
                                }

                                return $client->trashed() ? "{$client->name} (deleted)" : $client->name;
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::applyDefaultRates($set, $get)),
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
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::applyDefaultRates($set, $get)),
                        Select::make('education_candidate_id')
                            ->label('Candidate')
                            ->options(fn (): array => EducationCandidate::query()
                                ->get()
                                ->mapWithKeys(fn (EducationCandidate $candidate): array => [
                                    $candidate->id => trim("{$candidate->first_name} {$candidate->last_name}"),
                                ])
                                ->toArray()
                            )
                            ->getOptionLabelUsing(function (mixed $value): ?string {
                                $candidate = EducationCandidate::withTrashed()->find($value);

                                if (! $candidate) {
                                    return null;
                                }

                                $name = trim("{$candidate->first_name} {$candidate->last_name}");

                                return $candidate->trashed() ? "{$name} (deleted)" : $name;
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::applyDefaultRates($set, $get)),
                        DatePicker::make('start_date')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::regenerateDayPeriods($set, $get)),
                        DatePicker::make('end_date')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::regenerateDayPeriods($set, $get)),
                        Select::make('status')
                            ->options([
                                'provisional' => 'Provisional',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'completed' => 'Completed',
                            ])
                            ->required()
                            ->default('provisional'),
                    ]),

                Section::make('Daily Schedule')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => filled($get('start_date')))
                    ->schema([
                        Repeater::make('day_periods')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => filled($state['date'] ?? null)
                                ? Carbon::parse($state['date'])->format('D j M Y')
                                : null
                            )
                            ->schema([
                                Hidden::make('date'),
                                Select::make('period')
                                    ->label('Session')
                                    ->options(BookingDayPeriod::options())
                                    ->required(),
                            ])
                            ->columns(1)
                            ->columnSpanFull(),
                    ]),

                Section::make('Pay & Charge Rates')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('day_rate')
                            ->label('Day Pay Rate')
                            ->helperText('Defaults from the candidate\'s pay rate for this job title. Override if needed.')
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0),
                        TextInput::make('half_day_rate')
                            ->label('Half Day Pay Rate')
                            ->helperText('Defaults from the candidate\'s pay rate for this job title. Override if needed.')
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0),
                        TextInput::make('hourly_rate')
                            ->label('Hourly Pay Rate')
                            ->helperText('Defaults from the candidate\'s pay rate for this job title. Override if needed.')
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0),
                        TextInput::make('day_charge_rate')
                            ->label('Day Charge Rate')
                            ->helperText('Defaults from the client\'s charge rate for this job title. Override if needed.')
                            ->required()
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0),
                        TextInput::make('half_day_charge_rate')
                            ->label('Half Day Charge Rate')
                            ->helperText('Defaults from the client\'s charge rate for this job title. Override if needed.')
                            ->required()
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0),
                        TextInput::make('hourly_charge_rate')
                            ->label('Hourly Charge Rate')
                            ->helperText('Defaults from the client\'s charge rate for this job title. Override if needed.')
                            ->required()
                            ->numeric()
                            ->prefix('£')
                            ->step(0.01)
                            ->minValue(0),
                    ]),
            ]);
    }

    protected static function applyDefaultRates(Set $set, Get $get): void
    {
        $jobTitleId = $get('job_title_id');
        $candidateId = $get('education_candidate_id');
        $clientId = $get('education_client_id');

        if (filled($candidateId) && filled($jobTitleId)) {
            $payRate = PayRate::query()
                ->where('model_type', EducationCandidate::class)
                ->where('model_id', $candidateId)
                ->where('job_title_id', $jobTitleId)
                ->first();

            $set('day_rate', $payRate?->day_rate);
            $set('half_day_rate', $payRate?->half_day_rate);
            $set('hourly_rate', $payRate?->hourly_rate);
        }

        if (filled($clientId) && filled($jobTitleId)) {
            $chargeRate = PayRate::query()
                ->where('model_type', EducationClient::class)
                ->where('model_id', $clientId)
                ->where('job_title_id', $jobTitleId)
                ->first();

            $set('day_charge_rate', $chargeRate?->day_rate);
            $set('half_day_charge_rate', $chargeRate?->half_day_rate);
            $set('hourly_charge_rate', $chargeRate?->hourly_rate);
        }
    }

    protected static function regenerateDayPeriods(Set $set, Get $get): void
    {
        $startDate = $get('start_date');

        if (blank($startDate)) {
            $set('day_periods', []);

            return;
        }

        $endDate = $get('end_date') ?: $startDate;

        $existingPeriods = collect($get('day_periods') ?? [])
            ->filter(fn (array $entry): bool => filled($entry['date'] ?? null))
            ->keyBy('date');

        $dayPeriods = collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn (Carbon $date): array => [
                'date' => $date->toDateString(),
                'period' => $existingPeriods->get($date->toDateString())['period'] ?? BookingDayPeriod::FullDay->value,
            ])
            ->values()
            ->all();

        $set('day_periods', $dayPeriods);
    }
}
