<?php

namespace App\Filament\Resources\EducationBookings\Schemas;

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Models\EducationBooking;
use App\Models\EducationBookingDayPeriod;
use App\Models\EducationCandidate;
use App\Models\EducationClient;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Services\Booking\BookingOverlap;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
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
                        Select::make('candidate_id')
                            ->label('Candidate')
                            ->options(fn (?EducationBooking $record): array => EducationCandidate::query()
                                ->when(
                                    ! $record,
                                    fn ($query) => $query->whereHas(
                                        'statuses.status',
                                        fn ($statusQuery) => $statusQuery->where('name', 'Live')
                                    )
                                )
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
                            ->options(BookingStatus::options())
                            ->required()
                            ->default(BookingStatus::Upcoming->value),
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
                            ->dehydrated(false)
                            ->itemLabel(fn (array $state): ?string => filled($state['date'] ?? null)
                                ? Carbon::parse($state['date'])->format('D j M Y')
                                : null
                            )
                            ->rule(function (Get $get, ?EducationBooking $record): Closure {
                                return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $conflicts = BookingOverlap::conflictingDates(
                                        $get('candidate_id'),
                                        $value ?? [],
                                        $record?->id,
                                    );

                                    if ($conflicts->isEmpty()) {
                                        return;
                                    }

                                    $dates = $conflicts->map(fn (string $date): string => Carbon::parse($date)->format('jS M Y'))->implode(', ');

                                    $fail("This candidate already has a booking that overlaps on: {$dates}.");
                                };
                            })
                            ->schema([
                                Hidden::make('date'),
                                Select::make('period')
                                    ->label('Session')
                                    ->options(BookingDayPeriod::options())
                                    ->required()
                                    ->live(),
                                TimePicker::make('time_from')
                                    ->label('From')
                                    ->seconds(false)
                                    ->required(fn (Get $get): bool => $get('period') === BookingDayPeriod::Hours->value)
                                    ->visible(fn (Get $get): bool => $get('period') === BookingDayPeriod::Hours->value),
                                TimePicker::make('time_to')
                                    ->label('To')
                                    ->seconds(false)
                                    ->required(fn (Get $get): bool => $get('period') === BookingDayPeriod::Hours->value)
                                    ->visible(fn (Get $get): bool => $get('period') === BookingDayPeriod::Hours->value),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Pay & Charge Rates')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('day_rate')
                                    ->label('Day Pay Rate')
                                    ->helperText('Defaults from the candidate\'s pay rate for this job title. Override if needed.')
                                    ->numeric()
                                    ->prefix('£')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => static::dayRateVisible($get)),
                                TextInput::make('half_day_rate')
                                    ->label('Half Day Pay Rate')
                                    ->helperText('Defaults from the candidate\'s pay rate for this job title. Override if needed.')
                                    ->numeric()
                                    ->prefix('£')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => static::halfDayRateVisible($get)),
                                TextInput::make('hourly_rate')
                                    ->label('Hourly Pay Rate')
                                    ->helperText('Defaults from the candidate\'s pay rate for this job title. Override if needed.')
                                    ->numeric()
                                    ->prefix('£')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => static::hourlyRateVisible($get)),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('day_charge_rate')
                                    ->label('Day Charge Rate')
                                    ->helperText('Defaults from the client\'s charge rate for this job title. Override if needed.')
                                    ->required()
                                    ->numeric()
                                    ->prefix('£')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => static::dayRateVisible($get)),
                                TextInput::make('half_day_charge_rate')
                                    ->label('Half Day Charge Rate')
                                    ->helperText('Defaults from the client\'s charge rate for this job title. Override if needed.')
                                    ->required()
                                    ->numeric()
                                    ->prefix('£')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => static::halfDayRateVisible($get)),
                                TextInput::make('hourly_charge_rate')
                                    ->label('Hourly Charge Rate')
                                    ->helperText('Defaults from the client\'s charge rate for this job title. Override if needed.')
                                    ->required()
                                    ->numeric()
                                    ->prefix('£')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => static::hourlyRateVisible($get)),
                            ]),
                    ]),
            ]);
    }

    protected static function dayRateVisible(Get $get): bool
    {
        $periods = collect($get('day_periods') ?? [])->pluck('period')->filter();

        return $periods->isEmpty() || $periods->contains(BookingDayPeriod::FullDay->value);
    }

    protected static function halfDayRateVisible(Get $get): bool
    {
        $periods = collect($get('day_periods') ?? [])->pluck('period')->filter();

        return $periods->contains(BookingDayPeriod::Am->value) || $periods->contains(BookingDayPeriod::Pm->value);
    }

    protected static function hourlyRateVisible(Get $get): bool
    {
        $periods = collect($get('day_periods') ?? [])->pluck('period')->filter();

        return $periods->contains(BookingDayPeriod::Hours->value);
    }

    protected static function applyDefaultRates(Set $set, Get $get): void
    {
        $rates = static::defaultRates($get('candidate_id'), $get('education_client_id'), $get('job_title_id'));

        foreach ($rates as $key => $value) {
            $set($key, $value);
        }
    }

    /** @return array<string, mixed> */
    public static function defaultRates(mixed $candidateId, mixed $clientId, mixed $jobTitleId): array
    {
        $rates = [];

        if (filled($candidateId) && filled($jobTitleId)) {
            $payRate = PayRate::query()
                ->where('model_type', EducationCandidate::class)
                ->where('model_id', $candidateId)
                ->where('job_title_id', $jobTitleId)
                ->first();

            $rates['day_rate'] = $payRate?->day_rate;
            $rates['half_day_rate'] = $payRate?->half_day_rate;
            $rates['hourly_rate'] = $payRate?->hourly_rate;
        }

        if (filled($clientId) && filled($jobTitleId)) {
            $chargeRate = PayRate::query()
                ->where('model_type', EducationClient::class)
                ->where('model_id', $clientId)
                ->where('job_title_id', $jobTitleId)
                ->first();

            $rates['day_charge_rate'] = $chargeRate?->day_rate;
            $rates['half_day_charge_rate'] = $chargeRate?->half_day_rate;
            $rates['hourly_charge_rate'] = $chargeRate?->hourly_rate;
        }

        return $rates;
    }

    protected static function regenerateDayPeriods(Set $set, Get $get): void
    {
        $set('day_periods', static::dayPeriodsForRange($get('start_date'), $get('end_date'), $get('day_periods') ?? []));
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @return array<int, array{date: string, period: string, time_from: ?string, time_to: ?string}>
     */
    public static function dayPeriodsForRange(mixed $startDate, mixed $endDate, array $existing = []): array
    {
        if (blank($startDate)) {
            return [];
        }

        $endDate = $endDate ?: $startDate;

        $existingPeriods = collect($existing)
            ->filter(fn (array $entry): bool => filled($entry['date'] ?? null))
            ->keyBy('date');

        return collect(CarbonPeriod::create($startDate, $endDate))
            ->map(function (Carbon $date) use ($existingPeriods): array {
                $existing = $existingPeriods->get($date->toDateString());

                return [
                    'date' => $date->toDateString(),
                    'period' => $existing['period'] ?? BookingDayPeriod::FullDay->value,
                    'time_from' => $existing['time_from'] ?? null,
                    'time_to' => $existing['time_to'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array{date: string, period: string, time_from: ?string, time_to: ?string}> */
    public static function loadDayPeriods(EducationBooking $record): array
    {
        return $record->dayPeriods()
            ->get()
            ->map(fn (EducationBookingDayPeriod $period): array => [
                'date' => $period->date->toDateString(),
                'period' => $period->period->value,
                'time_from' => $period->time_from,
                'time_to' => $period->time_to,
            ])
            ->values()
            ->all();
    }

    /** @param  array<int, array<string, mixed>>|null  $items */
    public static function syncDayPeriods(EducationBooking $record, ?array $items): void
    {
        $items = collect($items ?? [])->filter(fn (array $item): bool => filled($item['date'] ?? null));

        $record->dayPeriods()->whereNotIn('date', $items->pluck('date')->all() ?: [''])->delete();

        foreach ($items as $item) {
            $record->dayPeriods()->updateOrCreate(
                ['date' => $item['date']],
                [
                    'company_id' => $record->company_id,
                    'period' => $item['period'] ?? BookingDayPeriod::FullDay->value,
                    'time_from' => $item['time_from'] ?? null,
                    'time_to' => $item['time_to'] ?? null,
                ],
            );
        }
    }
}
