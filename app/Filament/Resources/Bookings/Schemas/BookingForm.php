<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\Integration;
use App\Enums\PaymentMethod;
use App\Filament\Forms\Components\DayScheduleCalendar;
use App\Filament\Widgets\BookingTimesheetOverview;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Services\Booking\BookingEligibility;
use App\Services\Booking\BookingOverlap;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // Kept outside the Tabs so a failed sync is visible immediately
                // on page load, matching ClientForm's identical placement —
                // nested inside a non-default tab it'd be missed entirely.
                Section::make('Payroll Submission Failed')
                    ->columnSpanFull()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->visible(fn (?Booking $record): bool => $record && static::currentProviderErrors($record)->isNotEmpty())
                    ->schema([
                        Textarea::make('payroll_provider_errors')
                            ->hiddenLabel()
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3)
                            ->columnSpanFull()
                            ->afterStateHydrated(function (Textarea $component, ?Booking $record): void {
                                if ($record) {
                                    $component->state(static::currentProviderErrors($record)->implode("\n"));
                                }
                            }),
                    ]),

                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('Details')
                            ->schema(static::detailsTabSchema()),

                        Tab::make('Timesheets')
                            ->schema([
                                LivewireComponent::make(BookingTimesheetOverview::class)
                                    ->key('booking-timesheet-overview')
                                    ->hidden(fn (?Model $record): bool => $record === null),
                            ]),
                    ]),
            ]);
    }

    /** @return array<int, Component> */
    protected static function detailsTabSchema(): array
    {
        return [
            Section::make('Booking Details')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('client_id')
                        ->label('Client')
                        ->options(fn (): array => Client::query()
                            ->visibleToCurrentUser()
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
                        ->afterStateUpdated(fn (Set $set, Get $get) => static::applyDefaultRates($set, $get))
                        ->rule(function (Get $get): Closure {
                            return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');
                                $candidate = $candidateModelClass ? $candidateModelClass::find($get('candidate_id')) : null;

                                $reason = BookingEligibility::disallowedJobTitleReason($candidate, $value ? (int) $value : null);

                                if ($reason) {
                                    $fail($reason);
                                }
                            };
                        }),
                    Select::make('candidate_id')
                        ->label('Candidate')
                        ->options(function (?Booking $record): array {
                            $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

                            if (! $candidateModelClass) {
                                return [];
                            }

                            $candidates = $candidateModelClass::query()
                                ->whereHas(
                                    'latestStatus.status',
                                    fn ($statusQuery) => $statusQuery->where('name', 'Live')
                                )
                                ->get();

                            // Editing a booking must keep offering its already-assigned
                            // candidate even if their status has since moved off Live —
                            // otherwise saving the form with no other changes would
                            // silently blank the candidate out.
                            if ($record?->candidate_id && ! $candidates->contains('id', $record->candidate_id)) {
                                $existing = $candidateModelClass::withTrashed()->find($record->candidate_id);

                                if ($existing) {
                                    $candidates->push($existing);
                                }
                            }

                            return $candidates
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
                        ->default(BookingStatus::Upcoming->value)
                        // Status moves automatically when payroll runs (and the
                        // booking can be deleted if it needs correcting), so it's
                        // not something staff should set by hand on creation —
                        // only ever visible as a read-only indicator afterwards.
                        ->hidden(fn (?Booking $record): bool => $record === null)
                        ->disabled(fn (?Booking $record): bool => $record !== null)
                        ->dehydrated(),
                    Textarea::make('notes')
                        ->label('Client Notes')
                        ->helperText('Submitted by the client when they requested this booking.')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?Booking $record): bool => filled($record?->notes))
                        ->columnSpanFull(),
                ]),

            Section::make('Daily Schedule')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => filled($get('start_date')))
                ->schema([
                    CheckboxList::make('days_of_week')
                        ->label('Repeat on')
                        ->helperText('Only these weekdays are included when the schedule below is (re)generated from the date range — e.g. pick Thursday and Friday only for a booking that runs every Thursday and Friday between the start and end date.')
                        ->options([
                            '1' => 'Monday',
                            '2' => 'Tuesday',
                            '3' => 'Wednesday',
                            '4' => 'Thursday',
                            '5' => 'Friday',
                            '6' => 'Saturday',
                            '7' => 'Sunday',
                        ])
                        ->default(['1', '2', '3', '4', '5', '6', '7'])
                        ->columns(7)
                        ->dehydrated(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set, Get $get) => static::regenerateDayPeriods($set, $get)),
                    DayScheduleCalendar::make('day_periods')
                        ->hiddenLabel()
                        ->live()
                        ->dehydrated(false)
                        ->rule(function (Get $get, ?Booking $record): Closure {
                            return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                $missingTimes = collect($value ?? [])
                                    ->reject(fn (array $entry): bool => $entry['cancelled'] ?? false)
                                    ->filter(fn (array $entry): bool => ($entry['period'] ?? null) === BookingDayPeriod::Hours->value)
                                    ->filter(fn (array $entry): bool => blank($entry['time_from'] ?? null) || blank($entry['time_to'] ?? null));

                                if ($missingTimes->isNotEmpty()) {
                                    $dates = $missingTimes->pluck('date')->map(fn (string $date): string => Carbon::parse($date)->format('jS M Y'))->implode(', ');

                                    $fail("Enter a from and to time for these Hours days: {$dates}.");
                                }

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

                                if ($conflicts->isNotEmpty()) {
                                    $dates = $conflicts->map(fn (string $date): string => Carbon::parse($date)->format('jS M Y'))->implode(', ');

                                    $fail("This candidate already has a booking that overlaps on: {$dates}.");
                                }

                                $unavailable = BookingEligibility::unavailableDates(
                                    $candidateModelClass,
                                    $get('candidate_id'),
                                    $value ?? [],
                                );

                                if ($unavailable->isNotEmpty()) {
                                    $dates = $unavailable->map(fn (string $date): string => Carbon::parse($date)->format('jS M Y'))->implode(', ');

                                    $fail("This candidate is not available on: {$dates}.");
                                }
                            };
                        })
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
                                ->live(onBlur: true)
                                ->visible(fn (Get $get): bool => static::dayRateVisible($get)),
                            TextInput::make('half_day_rate')
                                ->label('Half Day Pay Rate')
                                ->helperText('Defaults from the candidate\'s pay rate for this job title. Override if needed.')
                                ->numeric()
                                ->prefix('£')
                                ->step(0.01)
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->visible(fn (Get $get): bool => static::halfDayRateVisible($get)),
                            TextInput::make('hourly_rate')
                                ->label('Hourly Pay Rate')
                                ->helperText('Defaults from the candidate\'s pay rate for this job title. Override if needed.')
                                ->numeric()
                                ->prefix('£')
                                ->step(0.01)
                                ->minValue(0)
                                ->live(onBlur: true)
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
                                ->live(onBlur: true)
                                ->visible(fn (Get $get): bool => static::dayRateVisible($get)),
                            TextInput::make('half_day_charge_rate')
                                ->label('Half Day Charge Rate')
                                ->helperText('Defaults from the client\'s charge rate for this job title. Override if needed.')
                                ->required()
                                ->numeric()
                                ->prefix('£')
                                ->step(0.01)
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->visible(fn (Get $get): bool => static::halfDayRateVisible($get)),
                            TextInput::make('hourly_charge_rate')
                                ->label('Hourly Charge Rate')
                                ->helperText('Defaults from the client\'s charge rate for this job title. Override if needed.')
                                ->required()
                                ->numeric()
                                ->prefix('£')
                                ->step(0.01)
                                ->minValue(0)
                                ->live(onBlur: true)
                                ->visible(fn (Get $get): bool => static::hourlyRateVisible($get)),
                        ]),
                ]),

            Section::make('Margin Calculator')
                ->columnSpanFull()
                ->description('Calculated from the scheduled days below and the rates above.')
                ->schema([
                    Placeholder::make('margin_payment_method')
                        ->label('Payment Method')
                        ->content(fn (Get $get): string => static::marginBreakdown($get)['paymentMethodLabel']),
                    Placeholder::make('margin_oncosts')
                        ->label('Employer Oncosts')
                        ->content(fn (Get $get): string => static::marginBreakdown($get)['oncostsLabel']),
                    Placeholder::make('margin_total_pay')
                        ->label('Total Pay Cost')
                        ->content(fn (Get $get): string => '£'.number_format(static::marginBreakdown($get)['totalPay'], 2)),
                    Placeholder::make('margin_total_charge')
                        ->label('Total Charge')
                        ->content(fn (Get $get): string => '£'.number_format(static::marginBreakdown($get)['totalCharge'], 2)),
                    Placeholder::make('margin_net')
                        ->label('Net Margin')
                        ->content(fn (Get $get): string => static::marginBreakdown($get)['marginLabel']),
                    View::make('filament.forms.components.margin-daily-breakdown')
                        ->viewData(fn (Get $get): array => ['rows' => static::dailyBreakdown($get)])
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Section::make('Payroll Provider')
                ->hidden()
                ->schema([
                    TextInput::make('payroll_provider_id')
                        ->label('Payroll Provider ID')
                        ->helperText('This booking\'s existing Placement ID in the agency\'s payroll provider, if one already exists there.')
                        ->dehydrated(false)
                        ->afterStateHydrated(function (TextInput $component, ?Booking $record): void {
                            $provider = Auth::user()->company->payroll_provider;

                            if ($record && $provider instanceof Integration) {
                                $component->state($record->providerExternalId($provider));
                            }
                        }),
                ]),

            Section::make('Payroll')
                ->visible(fn (?Booking $record): bool => $record !== null)
                ->schema([
                    // Shown regardless of whether the company currently has
                    // Evertime enabled as its active payroll_provider — a
                    // booking can already have a synced/manually-entered ID
                    // from before that toggle changed, or before it's set at
                    // all, and that shouldn't hide an ID that already exists.
                    TextEntry::make('payroll_provider_id_display')
                        ->label('Payroll Provider ID')
                        ->getStateUsing(fn (?Booking $record): ?string => $record?->providerExternalId(Integration::Evertime))
                        ->placeholder('Not yet synced'),
                ]),
        ];
    }

    /** @return Collection<int, string> */
    protected static function currentProviderErrors(Booking $record): Collection
    {
        $provider = $record->company->payroll_provider;

        if (! $provider instanceof Integration) {
            return collect();
        }

        return collect($record->providerErrors()->where('provider', $provider->value)->value('errors') ?? []);
    }

    /** @return Collection<int, string> */
    protected static function activePeriods(Get $get): Collection
    {
        return collect($get('day_periods') ?? [])
            ->reject(fn (array $entry): bool => $entry['cancelled'] ?? false)
            ->pluck('period')
            ->filter();
    }

    /**
     * Applied to the pay cost only for a PAYE candidate — this agency's
     * standard approximation of employer's National Insurance and other
     * statutory on-costs. An umbrella company candidate is invoiced as a
     * single fee that already covers their own employment costs, so there's
     * no additional on-cost to the agency on top of the pay rate for them.
     */
    private const PAYE_ONCOST_RATE = 0.15;

    /**
     * @return array{
     *     paymentMethod: ?PaymentMethod,
     *     paymentMethodLabel: string,
     *     totalPay: float,
     *     totalCharge: float,
     *     oncosts: float,
     *     oncostsLabel: string,
     *     margin: float,
     *     marginLabel: string,
     * }
     */
    protected static function marginBreakdown(Get $get): array
    {
        $dayAmounts = static::activeDayAmounts($get);

        $totalPay = $dayAmounts->sum('pay');
        $totalCharge = $dayAmounts->sum('charge');

        $paymentMethod = static::candidatePaymentMethod($get('candidate_id'));
        $oncosts = $paymentMethod === PaymentMethod::Paye ? round($totalPay * self::PAYE_ONCOST_RATE, 2) : 0.0;
        $margin = round($totalCharge - $totalPay - $oncosts, 2);

        $paymentMethodLabel = match ($paymentMethod) {
            PaymentMethod::Paye => 'PAYE',
            PaymentMethod::Umbrella => 'Umbrella',
            null => 'Not set',
        };

        $oncostsLabel = match (true) {
            $paymentMethod === PaymentMethod::Paye => '£'.number_format($oncosts, 2).' ('.(self::PAYE_ONCOST_RATE * 100).'% of pay, PAYE)',
            $paymentMethod === PaymentMethod::Umbrella => '£0.00 (umbrella company invoices their own costs)',
            default => '£0.00 (no payment method set on the candidate)',
        };

        $marginPercent = $totalCharge > 0 ? round(($margin / $totalCharge) * 100, 1) : 0.0;
        $marginLabel = '£'.number_format($margin, 2)." ({$marginPercent}%)";

        return [
            'paymentMethod' => $paymentMethod,
            'paymentMethodLabel' => $paymentMethodLabel,
            'totalPay' => round($totalPay, 2),
            'totalCharge' => round($totalCharge, 2),
            'oncosts' => $oncosts,
            'oncostsLabel' => $oncostsLabel,
            'margin' => $margin,
            'marginLabel' => $marginLabel,
        ];
    }

    /** @return Collection<int, array{date: ?string, period: string, pay: float, charge: float}> */
    private static function activeDayAmounts(Get $get): Collection
    {
        $payRates = [
            BookingDayPeriod::FullDay->value => (float) ($get('day_rate') ?? 0),
            BookingDayPeriod::Am->value => (float) ($get('half_day_rate') ?? 0),
            BookingDayPeriod::Pm->value => (float) ($get('half_day_rate') ?? 0),
            BookingDayPeriod::Hours->value => (float) ($get('hourly_rate') ?? 0),
        ];

        $chargeRates = [
            BookingDayPeriod::FullDay->value => (float) ($get('day_charge_rate') ?? 0),
            BookingDayPeriod::Am->value => (float) ($get('half_day_charge_rate') ?? 0),
            BookingDayPeriod::Pm->value => (float) ($get('half_day_charge_rate') ?? 0),
            BookingDayPeriod::Hours->value => (float) ($get('hourly_charge_rate') ?? 0),
        ];

        return collect($get('day_periods') ?? [])
            ->reject(fn (array $entry): bool => $entry['cancelled'] ?? false)
            ->filter(fn (array $entry): bool => filled($entry['period'] ?? null))
            ->map(function (array $entry) use ($payRates, $chargeRates): array {
                $period = $entry['period'];
                $units = $period === BookingDayPeriod::Hours->value
                    ? static::entryHours($entry)
                    : 1.0;

                return [
                    'date' => $entry['date'] ?? null,
                    'period' => $period,
                    'pay' => ($payRates[$period] ?? 0) * $units,
                    'charge' => ($chargeRates[$period] ?? 0) * $units,
                ];
            })
            ->values();
    }

    /** @return array<int, array{date: string, periodLabel: string, payLabel: string, chargeLabel: string, marginLabel: string}> */
    protected static function dailyBreakdown(Get $get): array
    {
        $paymentMethod = static::candidatePaymentMethod($get('candidate_id'));

        $periodLabels = [
            BookingDayPeriod::FullDay->value => 'Full Day',
            BookingDayPeriod::Am->value => 'AM',
            BookingDayPeriod::Pm->value => 'PM',
            BookingDayPeriod::Hours->value => 'Hours',
        ];

        return static::activeDayAmounts($get)
            ->sortBy('date')
            ->map(function (array $day) use ($paymentMethod, $periodLabels): array {
                $oncost = $paymentMethod === PaymentMethod::Paye ? round($day['pay'] * self::PAYE_ONCOST_RATE, 2) : 0.0;
                $margin = round($day['charge'] - $day['pay'] - $oncost, 2);

                return [
                    'date' => $day['date'] ? Carbon::parse($day['date'])->format('D j M Y') : 'Unknown date',
                    'periodLabel' => $periodLabels[$day['period']] ?? $day['period'],
                    'payLabel' => '£'.number_format($day['pay'], 2),
                    'chargeLabel' => '£'.number_format($day['charge'], 2),
                    'marginLabel' => '£'.number_format($margin, 2),
                ];
            })
            ->values()
            ->all();
    }

    private static function entryHours(array $entry): float
    {
        $from = $entry['time_from'] ?? null;
        $to = $entry['time_to'] ?? null;

        if (! $from || ! $to) {
            return 0.0;
        }

        return round(abs(Carbon::parse($from)->diffInMinutes(Carbon::parse($to))) / 60, 2);
    }

    private static function candidatePaymentMethod(mixed $candidateId): ?PaymentMethod
    {
        if (blank($candidateId)) {
            return null;
        }

        $candidateModelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if (! $candidateModelClass) {
            return null;
        }

        return $candidateModelClass::find($candidateId)?->payment_method;
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
        $set('day_periods', static::dayPeriodsForRange(
            $get('start_date'),
            $get('end_date'),
            $get('day_periods') ?? [],
            $get('days_of_week') ?? [],
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<int, string|int>  $daysOfWeek  ISO weekday numbers (1 = Monday .. 7 = Sunday) to include.
     *                                              An empty array means "no filter" — every day in the range —
     *                                              so existing callers/bookings are unaffected.
     * @param  ?bool  $weekendsDefaultToNA  Schools only operate on weekdays, so a freshly generated
     *                                      Saturday/Sunday defaults to N/A rather than a full day for
     *                                      Education — Healthcare (and anything else) runs 7 days a
     *                                      week, so weekends there default to Full Day like any other
     *                                      day. Null (the default) infers this from active_industry(),
     *                                      which only reflects the logged-in *staff* member's current
     *                                      sector — callers outside that context (e.g. the client
     *                                      portal, where a client has a fixed industry rather than a
     *                                      switchable one) must pass this explicitly.
     * @return array<int, array{date: string, period: string, time_from: ?string, time_to: ?string, cancelled: bool}>
     */
    public static function dayPeriodsForRange(mixed $startDate, mixed $endDate, array $existing = [], array $daysOfWeek = [], ?bool $weekendsDefaultToNA = null): array
    {
        if (blank($startDate)) {
            return [];
        }

        $endDate = $endDate ?: $startDate;
        $weekendsDefaultToNA ??= active_industry() === 'education';

        $existingPeriods = collect($existing)
            ->filter(fn (array $entry): bool => filled($entry['date'] ?? null))
            ->keyBy('date');

        $allowedWeekdays = collect($daysOfWeek)->map(fn (string|int $day): int => (int) $day);

        return collect(CarbonPeriod::create($startDate, $endDate))
            ->when(
                $allowedWeekdays->isNotEmpty(),
                fn (Collection $dates) => $dates->filter(fn (Carbon $date): bool => $allowedWeekdays->contains($date->isoWeekday())),
            )
            ->map(function (Carbon $date) use ($existingPeriods, $weekendsDefaultToNA): array {
                $existing = $existingPeriods->get($date->toDateString());

                $isWeekend = $date->isWeekend() && $weekendsDefaultToNA;

                return [
                    'date' => $date->toDateString(),
                    'period' => $existing['period'] ?? BookingDayPeriod::FullDay->value,
                    'time_from' => $existing['time_from'] ?? null,
                    'time_to' => $existing['time_to'] ?? null,
                    'cancelled' => $existing['cancelled'] ?? $isWeekend,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $dayPeriods
     * @return array<int, array<string, mixed>>
     */
    public static function withPeriodAppliedToSelected(array $dayPeriods, BookingDayPeriod $period): array
    {
        return collect($dayPeriods)
            ->map(function (array $entry) use ($period): array {
                if ($entry['selected'] ?? false) {
                    $entry['period'] = $period->value;
                }

                return $entry;
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $dayPeriods
     * @return array<int, array<string, mixed>>
     */
    public static function withCancelledAppliedToSelected(array $dayPeriods, bool $cancelled): array
    {
        return collect($dayPeriods)
            ->map(function (array $entry) use ($cancelled): array {
                if ($entry['selected'] ?? false) {
                    $entry['cancelled'] = $cancelled;
                }

                return $entry;
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $dates
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<string, string>  $defaultPeriods  Per-date fallback period (e.g. from the candidate's AM/PM availability), keyed by date. Falls back to Full Day where a date has no entry.
     * @return array<int, array{date: string, period: string, time_from: ?string, time_to: ?string, cancelled: bool}>
     */
    public static function dayPeriodsForDates(array $dates, array $existing = [], array $defaultPeriods = []): array
    {
        if (empty($dates)) {
            return [];
        }

        $existingPeriods = collect($existing)
            ->filter(fn (array $entry): bool => filled($entry['date'] ?? null))
            ->keyBy('date');

        return collect($dates)
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $date) use ($existingPeriods, $defaultPeriods): array {
                $existing = $existingPeriods->get($date);

                return [
                    'date' => $date,
                    'period' => $existing['period'] ?? $defaultPeriods[$date] ?? BookingDayPeriod::FullDay->value,
                    'time_from' => $existing['time_from'] ?? null,
                    'time_to' => $existing['time_to'] ?? null,
                    'cancelled' => $existing['cancelled'] ?? false,
                ];
            })
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

            // A day marked N/A that was never actually booked (e.g. a
            // weekend on a schedule that only works weekdays) shouldn't
            // leave a row behind at all. One that WAS booked and has since
            // been cancelled keeps its row — cancelled, not deleted — so
            // there's an audit trail of the change.
            if ($isCancelled && ! $existing) {
                continue;
            }

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
