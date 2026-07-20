<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Industry;
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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Booking Details')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('client_id')
                            ->label('Client')
                            ->options(fn (): array => Client::query()
                                ->pluck('name', 'id')
                                ->toArray()
                            )
                            ->getOptionLabelUsing(function (mixed $value): ?string {
                                $client = Client::withTrashed()->find($value);

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
                            ->options(function (?Booking $record): array {
                                $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

                                if (! $candidateModelClass) {
                                    return [];
                                }

                                return $candidateModelClass::query()
                                    ->when(
                                        ! $record,
                                        fn ($query) => $query->whereHas(
                                            'statuses.status',
                                            fn ($statusQuery) => $statusQuery->where('name', 'Live')
                                        )
                                    )
                                    ->get()
                                    ->mapWithKeys(fn (Model $candidate): array => [
                                        $candidate->id => trim("{$candidate->first_name} {$candidate->last_name}"),
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function (mixed $value): ?string {
                                $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

                                $candidate = $candidateModelClass ? $candidateModelClass::withTrashed()->find($value) : null;

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
                                    .(($state['cancelled'] ?? false) ? ' (Cancelled)' : '')
                                    .(($state['disputed'] ?? false) ? ' (Disputed)' : '')
                                : null
                            )
                            ->rule(function (Get $get, ?Booking $record): Closure {
                                return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

                                    if (! $candidateModelClass) {
                                        return;
                                    }

                                    $conflicts = BookingOverlap::conflictingDates(
                                        $candidateModelClass,
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
                                Toggle::make('cancelled')
                                    ->label('Cancelled')
                                    ->live()
                                    ->columnSpanFull(),
                                Textarea::make('dispute_reason')
                                    ->label('Dispute Reason')
                                    ->helperText('Raised by the client when confirming this timesheet.')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->rows(2)
                                    ->visible(fn (Get $get): bool => (bool) $get('disputed'))
                                    ->columnSpanFull(),
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

    /** @return Collection<int, string> */
    protected static function activePeriods(Get $get): Collection
    {
        return collect($get('day_periods') ?? [])
            ->reject(fn (array $entry): bool => $entry['cancelled'] ?? false)
            ->pluck('period')
            ->filter();
    }

    protected static function dayRateVisible(Get $get): bool
    {
        $periods = static::activePeriods($get);

        return $periods->isEmpty() || $periods->contains(BookingDayPeriod::FullDay->value);
    }

    protected static function halfDayRateVisible(Get $get): bool
    {
        $periods = static::activePeriods($get);

        return $periods->contains(BookingDayPeriod::Am->value) || $periods->contains(BookingDayPeriod::Pm->value);
    }

    protected static function hourlyRateVisible(Get $get): bool
    {
        $periods = static::activePeriods($get);

        return $periods->contains(BookingDayPeriod::Hours->value);
    }

    protected static function applyDefaultRates(Set $set, Get $get): void
    {
        $rates = static::defaultRates($get('candidate_id'), $get('client_id'), $get('job_title_id'));

        foreach ($rates as $key => $value) {
            $set($key, $value);
        }
    }

    /** @return array<string, mixed> */
    public static function defaultRates(mixed $candidateId, mixed $clientId, mixed $jobTitleId): array
    {
        $rates = [];
        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if (filled($candidateId) && filled($jobTitleId) && $candidateModelClass) {
            $payRate = PayRate::query()
                ->where('model_type', $candidateModelClass)
                ->where('model_id', $candidateId)
                ->where('job_title_id', $jobTitleId)
                ->first();

            $rates['day_rate'] = $payRate?->day_rate;
            $rates['half_day_rate'] = $payRate?->half_day_rate;
            $rates['hourly_rate'] = $payRate?->hourly_rate;
        }

        if (filled($clientId) && filled($jobTitleId)) {
            $chargeRate = PayRate::query()
                ->where('model_type', Client::class)
                ->where('model_id', $clientId)
                ->where('job_title_id', $jobTitleId)
                ->first();

            $rates['day_charge_rate'] = $chargeRate?->day_rate;
            $rates['half_day_charge_rate'] = $chargeRate?->half_day_rate;
            $rates['hourly_charge_rate'] = $chargeRate?->hourly_rate;
        }

        return $rates;
    }

    /** @return array<string, mixed> */
    public static function ratesFromBooking(Booking $booking): array
    {
        return [
            'day_rate' => $booking->day_rate,
            'half_day_rate' => $booking->half_day_rate,
            'hourly_rate' => $booking->hourly_rate,
            'day_charge_rate' => $booking->day_charge_rate,
            'half_day_charge_rate' => $booking->half_day_charge_rate,
            'hourly_charge_rate' => $booking->hourly_charge_rate,
        ];
    }

    protected static function regenerateDayPeriods(Set $set, Get $get): void
    {
        $set('day_periods', static::dayPeriodsForRange($get('start_date'), $get('end_date'), $get('day_periods') ?? []));
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @return array<int, array{date: string, period: string, time_from: ?string, time_to: ?string, cancelled: bool}>
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
                    'cancelled' => $existing['cancelled'] ?? false,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array{date: string, period: string, time_from: ?string, time_to: ?string, cancelled: bool, disputed: bool, dispute_reason: ?string}> */
    public static function loadDayPeriods(Booking $record): array
    {
        return $record->dayPeriods()
            ->get()
            ->map(fn (BookingDay $period): array => [
                'date' => $period->date->toDateString(),
                'period' => $period->period->value,
                'time_from' => $period->time_from,
                'time_to' => $period->time_to,
                'cancelled' => $period->isCancelled(),
                'disputed' => $period->isDisputed(),
                'dispute_reason' => $period->dispute_reason,
            ])
            ->values()
            ->all();
    }

    /** @param  array<int, array<string, mixed>>|null  $items */
    public static function syncDayPeriods(Booking $record, ?array $items): void
    {
        $items = collect($items ?? [])->filter(fn (array $item): bool => filled($item['date'] ?? null));
        $submittedDates = $items->pluck('date')->all();

        $record->dayPeriods()
            ->get()
            ->reject(fn (BookingDay $dayPeriod): bool => in_array($dayPeriod->date->toDateString(), $submittedDates, true))
            ->each(fn (BookingDay $dayPeriod) => $dayPeriod->delete());

        foreach ($items as $item) {
            $isCancelled = (bool) ($item['cancelled'] ?? false);
            $existing = $record->dayPeriods()->whereDate('date', $item['date'])->first();

            $attributes = [
                'company_id' => $record->company_id,
                'date' => $item['date'],
                'period' => $item['period'] ?? BookingDayPeriod::FullDay->value,
                'time_from' => $item['time_from'] ?? null,
                'time_to' => $item['time_to'] ?? null,
                'cancelled_at' => $isCancelled ? ($existing?->cancelled_at ?? now()) : null,
            ];

            if ($existing) {
                $existing->update($attributes);
            } else {
                $record->dayPeriods()->create($attributes);
            }
        }
    }
}
