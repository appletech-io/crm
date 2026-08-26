<?php

namespace App\Filament\Resources\HealthcareCandidates\Pages;

use App\Actions\Candidates\HealthcareCandidateCreated;
use App\Enums\BookingDayPeriod;
use App\Enums\CandidateAvailabilityStatus;
use App\Enums\EmailTemplateAudience;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Support\AddToCandidatePoolAction;
use App\Filament\Support\SendCustomEmailAction;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\CandidateAvailability;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Client;
use App\Models\HealthcareCandidate;
use App\Services\Healthcare\CandidateSearchService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;

class ListHealthcareCandidates extends ListRecords implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = HealthcareCandidateResource::class;

    protected string $view = 'filament.resources.healthcare-candidates.pages.list-healthcare-candidates';

    public string $activeSection = 'search';

    public ?array $data = [];

    public ?float $searchLat = null;

    public ?float $searchLng = null;

    /** @var array<int, array<int, bool>> */
    public array $selectedDays = [];

    public function mount(): void
    {
        parent::mount();

        $this->form->fill(['radius_miles' => 10]);
        $this->search();
    }

    public function updatedActiveSection(): void
    {
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Candidate')
                ->modalHeading('Add Candidate')
                ->createAnother(false)
                ->modalWidth('sm')
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(HealthcareCandidate::class, 'email'),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data['consultant_id'] = auth()->id();

                    return $data;
                })
                ->after(function (HealthcareCandidate $record) {
                    HealthcareCandidateCreated::run($record);

                    return redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Search Candidates')
                    ->columns(3)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('name')
                            ->label('Name'),
                        TextInput::make('email')
                            ->label('Email'),
                        Select::make('skill_ids')
                            ->label('Skills')
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => CandidateSkill::query()
                                ->where('company_id', Auth::user()->company_id)
                                ->where('industry_id', active_industry_id())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray()
                            ),
                        Select::make('pool_ids')
                            ->label('Pools')
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => CandidatePool::query()
                                ->where('industry_id', active_industry_id())
                                ->where(fn ($query) => $query
                                    ->where('user_id', Auth::id())
                                    ->orWhere(fn ($q) => $q->where('company_pool', true)->whereNull('user_id'))
                                )
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray()
                            ),
                        Select::make('status_ids')
                            ->label('Status')
                            ->multiple()
                            ->searchable()
                            // Only meaningful on the "All Candidates" tab — the
                            // dedicated Search tab is always restricted to Live
                            // candidates (see CandidateSearchService), so a
                            // status picker there would be redundant.
                            ->visible(fn (ListHealthcareCandidates $livewire): bool => $livewire->activeSection === 'all')
                            ->options(fn (): array => CandidateStatus::query()
                                ->where('company_id', Auth::user()->company_id)
                                ->where('industry_id', active_industry_id())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray()
                            ),
                        Select::make('client_id')
                            ->label('Near Client')
                            ->placeholder('Any location')
                            ->searchable()
                            ->live()
                            ->options(fn (): array => Client::query()
                                ->where('consultant_id', Auth::id())
                                ->where('industry_id', active_industry_id())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray()
                            ),
                        TextInput::make('address')
                            ->label('Or Address / Postcode')
                            ->placeholder('Start typing an address or postcode…')
                            ->disabled(fn (Get $get): bool => filled($get('client_id'))),
                        Select::make('radius_miles')
                            ->label('Radius')
                            ->default(10)
                            ->options([
                                5 => '5 miles',
                                10 => '10 miles',
                                25 => '25 miles',
                                50 => '50 miles',
                            ]),
                        CheckboxList::make('days')
                            ->label('Available On')
                            ->columns(5)
                            ->columnSpanFull()
                            ->options([
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function search(): void
    {
        $data = $this->form->getState();

        $this->searchLat = null;
        $this->searchLng = null;

        if (filled($data['client_id'] ?? null)) {
            $this->resolveClientLocation((int) $data['client_id']);
        } elseif (filled($data['address'] ?? null)) {
            $this->resolveAddressLocation($data['address']);
        }

        $this->resetTable();
    }

    private function resolveClientLocation(int $clientId): void
    {
        $client = Client::find($clientId);

        if (! $client) {
            return;
        }

        if ($client->latitude === null || $client->longitude === null) {
            Notification::make()
                ->warning()
                ->title("{$client->name} hasn't been located on a map yet")
                ->body('Location filtering has been skipped.')
                ->send();

            return;
        }

        $this->searchLat = (float) $client->latitude;
        $this->searchLng = (float) $client->longitude;
    }

    private function resolveAddressLocation(string $address): void
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'key' => config('services.google.places_key'),
        ]);

        $result = $response->successful() ? $response->json('results.0.geometry.location') : null;

        if (! $result) {
            Notification::make()
                ->warning()
                ->title('Could not find that address')
                ->body('Location filtering has been skipped.')
                ->send();

            return;
        }

        $this->searchLat = (float) $result['lat'];
        $this->searchLng = (float) $result['lng'];
    }

    public function table(Table $table): Table
    {
        if ($this->activeSection !== 'search' && ! $this->hasActiveSearchFilters()) {
            return $table;
        }

        return $this->configureSearchTable($table);
    }

    /**
     * Whether the search form has any criteria filled in beyond its
     * defaults — used on the "All Candidates" tab to decide whether to keep
     * that tab's broader default (bare, unfiltered resource query) or switch
     * to configureSearchTable() once the user has actually searched for
     * something. That switch only applies the entered filter criteria on
     * this tab — CandidateSearchService's consultant-own/status "Live"
     * restriction is passed as false here, since "All Candidates" search
     * should still mean every consultant's candidates, any status.
     */
    private function hasActiveSearchFilters(): bool
    {
        $data = $this->data ?? [];

        return filled($data['name'] ?? null)
            || filled($data['email'] ?? null)
            || filled($data['skill_ids'] ?? null)
            || filled($data['client_id'] ?? null)
            || filled($data['address'] ?? null)
            || filled($data['days'] ?? null)
            || filled($data['pool_ids'] ?? null)
            || filled($data['status_ids'] ?? null);
    }

    protected function configureSearchTable(Table $table): Table
    {
        $weekStart = now()->startOfWeek(CarbonImmutable::MONDAY);

        return $table
            ->query(fn (): Builder => app(CandidateSearchService::class)
                ->search([
                    'name' => $this->data['name'] ?? null,
                    'email' => $this->data['email'] ?? null,
                    'skill_ids' => $this->data['skill_ids'] ?? null,
                    'days' => $this->data['days'] ?? null,
                    'lat' => $this->searchLat,
                    'lng' => $this->searchLng,
                    'radius_miles' => $this->data['radius_miles'] ?? null,
                    'pool_ids' => $this->data['pool_ids'] ?? null,
                    // Status is only ever offered as a filter on the "All
                    // Candidates" tab (see the field's visible() above) — the
                    // Search tab is hardcoded to Live via
                    // restrictToOwnLiveCandidates, so applying it there too
                    // would be redundant at best.
                    'status_ids' => $this->activeSection === 'all' ? ($this->data['status_ids'] ?? null) : null,
                ], restrictToOwnLiveCandidates: $this->activeSection === 'search')
                ->with([
                    'bookings' => fn ($query) => $query
                        ->whereHas('dayPeriods', fn ($q) => $q
                            ->whereDate('date', '>=', $weekStart->toDateString())
                            ->whereDate('date', '<=', $weekStart->copy()->addDays(4)->toDateString())
                            ->whereNull('cancelled_at'))
                        ->with(['dayPeriods' => fn ($q) => $q
                            ->whereDate('date', '>=', $weekStart->toDateString())
                            ->whereDate('date', '<=', $weekStart->copy()->addDays(4)->toDateString())
                            ->whereNull('cancelled_at')]),
                    'availabilities' => fn ($query) => $query
                        ->whereDate('date', '>=', $weekStart->toDateString())
                        ->whereDate('date', '<=', $weekStart->copy()->addDays(4)->toDateString()),
                ]))
            ->recordUrl(fn (HealthcareCandidate $record): string => HealthcareCandidateResource::getUrl('edit', ['record' => $record]))
            ->filters([])
            ->recordActions([
                Action::make('book')
                    ->label('Book')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('success')
                    ->visible(fn (HealthcareCandidate $record): bool => filled($this->selectedDatesFor($record->id, $weekStart)))
                    ->url(fn (HealthcareCandidate $record): string => BookingResource::getUrl('create', [
                        'candidate_id' => $record->id,
                        'client_id' => $this->data['client_id'] ?? null,
                        'dates' => $this->selectedDatesFor($record->id, $weekStart),
                        'periods' => $this->selectedPeriodsFor($record, $weekStart),
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SendCustomEmailAction::bulk(EmailTemplateAudience::Candidate),
                    AddToCandidatePoolAction::bulk(HealthcareCandidate::class),
                ]),
            ])
            ->columns([
                TextColumn::make('first_name')
                    ->label('First Name'),
                TextColumn::make('last_name')
                    ->label('Last Name'),
                TextColumn::make('phone')
                    ->placeholder('—'),
                TextColumn::make('average_rating')
                    ->label('Rating')
                    ->badge()
                    ->formatStateUsing(fn (?float $state, HealthcareCandidate $record): string => $state !== null
                        ? number_format($state, 1)." ★ ({$record->ratings_count})"
                        : 'Not yet rated')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('postcode')
                    ->label('Postcode')
                    ->placeholder('—'),
                TextColumn::make('availability_score')
                    ->label('Availability')
                    ->getStateUsing(fn (HealthcareCandidate $record): string => $this->availableDayCount($record, $weekStart).'/5 available')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $this->orderByAvailability($query, $direction, $weekStart))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distance')
                    ->label('Distance')
                    ->getStateUsing(function (HealthcareCandidate $record): ?string {
                        if ($this->searchLat === null || $this->searchLng === null || $record->latitude === null || $record->longitude === null) {
                            return null;
                        }

                        $miles = CandidateSearchService::distanceInMiles(
                            $this->searchLat,
                            $this->searchLng,
                            $record->latitude,
                            $record->longitude,
                        );

                        return number_format($miles, 1).' mi';
                    })
                    ->visible(fn (): bool => $this->searchLat !== null),
                ...$this->dayColumns($weekStart),
            ])
            ->paginated([10, 25, 50, 100, 250, 500, 'all'])
            ->emptyStateHeading('No candidates match your search');
    }

    /** @return array<int, IconColumn> */
    private function dayColumns(CarbonImmutable $weekStart): array
    {
        return collect(CandidateSearchService::WEEKDAYS)
            ->map(function (int $isoWeekday) use ($weekStart): IconColumn {
                $date = $weekStart->copy()->addDays($isoWeekday - 1);

                return IconColumn::make("day_{$isoWeekday}")
                    ->label($date->format('D'))
                    ->getStateUsing(fn (): bool => true)
                    ->icon(fn (HealthcareCandidate $record): string|Htmlable => $this->dayIcon(
                        $this->availabilityStatusFor($record, $date),
                        $this->isDaySelected($record->id, $isoWeekday),
                        $this->bookedCoverageFor($record, $date),
                    ))
                    ->color(fn (HealthcareCandidate $record): string => $this->dayColor(
                        $this->availabilityStatusFor($record, $date),
                    ))
                    ->tooltip(fn (HealthcareCandidate $record): string => $this->dayTooltip(
                        $this->availabilityStatusFor($record, $date),
                        $this->bookedCoverageFor($record, $date),
                    ))
                    ->action(function (HealthcareCandidate $record) use ($date, $isoWeekday): void {
                        if (! $this->isSelectableStatus($this->availabilityStatusFor($record, $date))) {
                            return;
                        }

                        $this->toggleDay($record->id, $isoWeekday);
                    });
            })
            ->all();
    }

    /**
     * The candidate's availability status for this date, from the
     * "availabilities" and "bookings.dayPeriods" relations already eager
     * loaded by configureSearchTable() — an active booking always wins over
     * whatever's stored, mirroring CandidateWeeklyAvailability. A null
     * result means no availability has been recorded for that day at all.
     */
    private function availabilityStatusFor(HealthcareCandidate $record, CarbonImmutable $date): ?string
    {
        $isBooked = $record->bookings->contains(
            fn (Booking $booking): bool => $booking->dayPeriods->contains(
                fn (BookingDay $dayPeriod): bool => $dayPeriod->date->isSameDay($date)
            )
        );

        if ($isBooked) {
            return CandidateAvailabilityStatus::Booked->value;
        }

        $stored = $record->availabilities->first(
            fn (CandidateAvailability $availability): bool => $availability->date->isSameDay($date)
        );

        return $stored?->status?->value;
    }

    private function isSelectableStatus(?string $status): bool
    {
        return in_array($status, [
            CandidateAvailabilityStatus::Available->value,
            CandidateAvailabilityStatus::AvailableAm->value,
            CandidateAvailabilityStatus::AvailablePm->value,
            null,
        ], true);
    }

    /**
     * How many of the 5 displayed weekdays this candidate is free — reuses
     * availabilityStatusFor()/isSelectableStatus() so the count always
     * agrees with what the day-by-day icons on the same row are showing.
     */
    private function availableDayCount(HealthcareCandidate $record, CarbonImmutable $weekStart): int
    {
        return collect(CandidateSearchService::WEEKDAYS)
            ->filter(fn (int $isoWeekday): bool => $this->isSelectableStatus(
                $this->availabilityStatusFor($record, $weekStart->copy()->addDays($isoWeekday - 1))
            ))
            ->count();
    }

    /**
     * Approximates availableDayCount() in SQL for sorting: 5 minus however
     * many of the displayed weekdays are booked (via booking_days) or
     * explicitly marked Not Available (via candidate_availabilities) — a
     * day with neither counts as available, mirroring isSelectableStatus()
     * treating "no data" as available rather than unknown.
     */
    private function orderByAvailability(Builder $query, string $direction, CarbonImmutable $weekStart): Builder
    {
        $weekEnd = $weekStart->copy()->addDays(4);

        // date columns are cast as 'date' on their models but stored with a
        // time component (e.g. '2026-08-10 00:00:00') — DATE() normalizes
        // that before comparing, on both MySQL and SQLite, rather than
        // relying on a raw string BETWEEN that's fragile at the boundaries.
        return $query->orderByRaw(
            '(5
                - (SELECT COUNT(DISTINCT DATE(bd.date)) FROM booking_days bd INNER JOIN bookings b ON b.id = bd.booking_id WHERE b.candidate_type = ? AND b.candidate_id = healthcare_candidates.id AND DATE(bd.date) BETWEEN ? AND ? AND bd.cancelled_at IS NULL)
                - (SELECT COUNT(*) FROM candidate_availabilities ca WHERE ca.candidate_type = ? AND ca.candidate_id = healthcare_candidates.id AND DATE(ca.date) BETWEEN ? AND ? AND ca.status = ?)
            ) '.$direction,
            [
                HealthcareCandidate::class, $weekStart->toDateString(), $weekEnd->toDateString(),
                HealthcareCandidate::class, $weekStart->toDateString(), $weekEnd->toDateString(), CandidateAvailabilityStatus::NotAvailable->value,
            ],
        );
    }

    /**
     * Whether an existing booking on this date covers the full day, just the
     * morning, or just the afternoon — only meaningful when
     * availabilityStatusFor() has returned Booked for the same date. A
     * "full_day"/"hours" period, or an AM booking alongside a separate PM
     * booking, both count as covering the whole day.
     */
    private function bookedCoverageFor(HealthcareCandidate $record, CarbonImmutable $date): string
    {
        $periods = $record->bookings
            ->flatMap(fn (Booking $booking) => $booking->dayPeriods)
            ->filter(fn (BookingDay $dayPeriod): bool => $dayPeriod->date->isSameDay($date))
            ->map(fn (BookingDay $dayPeriod): BookingDayPeriod => $dayPeriod->period);

        $hasAm = $periods->contains(BookingDayPeriod::Am);
        $hasPm = $periods->contains(BookingDayPeriod::Pm);
        $hasFullCoverage = $periods->contains(fn (BookingDayPeriod $period): bool => in_array($period, [BookingDayPeriod::FullDay, BookingDayPeriod::Hours], true));

        return match (true) {
            $hasFullCoverage || ($hasAm && $hasPm) => 'full',
            $hasAm => 'am',
            $hasPm => 'pm',
            default => 'full',
        };
    }

    private function dayIcon(?string $status, bool $isSelected, string $bookedCoverage): string|Htmlable
    {
        return match ($status) {
            CandidateAvailabilityStatus::Available->value => $isSelected ? 'heroicon-s-check-circle' : 'heroicon-o-check-circle',
            CandidateAvailabilityStatus::Booked->value => match ($bookedCoverage) {
                'am' => $this->halfCircleIcon(topFilled: true, fullyFilled: false),
                'pm' => $this->halfCircleIcon(topFilled: false, fullyFilled: false),
                default => 'heroicon-o-check-circle',
            },
            CandidateAvailabilityStatus::AvailableAm->value => $this->halfCircleIcon(topFilled: true, fullyFilled: $isSelected),
            CandidateAvailabilityStatus::AvailablePm->value => $this->halfCircleIcon(topFilled: false, fullyFilled: $isSelected),
            CandidateAvailabilityStatus::NotAvailable->value => 'heroicon-o-x-circle',
            default => $isSelected ? 'heroicon-s-question-mark-circle' : 'heroicon-o-question-mark-circle',
        };
    }

    private function dayColor(?string $status): string
    {
        return match ($status) {
            CandidateAvailabilityStatus::Available->value,
            CandidateAvailabilityStatus::AvailableAm->value,
            CandidateAvailabilityStatus::AvailablePm->value => 'success',
            CandidateAvailabilityStatus::Booked->value => 'info',
            CandidateAvailabilityStatus::NotAvailable->value => 'danger',
            default => 'warning',
        };
    }

    private function dayTooltip(?string $status, string $bookedCoverage): string
    {
        return match ($status) {
            CandidateAvailabilityStatus::Available->value => 'Available — click to select this day for booking',
            CandidateAvailabilityStatus::AvailableAm->value => 'Available AM — click to select this day for booking',
            CandidateAvailabilityStatus::AvailablePm->value => 'Available PM — click to select this day for booking',
            CandidateAvailabilityStatus::Booked->value => match ($bookedCoverage) {
                'am' => 'Already has a morning booking this day',
                'pm' => 'Already has an afternoon booking this day',
                default => 'Already has a booking this day',
            },
            CandidateAvailabilityStatus::NotAvailable->value => 'Marked as not available this day',
            default => 'Availability not set for this day — click to select for booking',
        };
    }

    /**
     * A circle outline with only the top (AM) or bottom (PM) half filled —
     * Heroicons has nothing like this, so it's a small hand-rolled SVG
     * matching the outline icon style used everywhere else in this column.
     * When selected, the whole circle fills in as a clear "chosen" state.
     */
    private function halfCircleIcon(bool $topFilled, bool $fullyFilled): Htmlable
    {
        if ($fullyFilled) {
            return new HtmlString(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9" /></svg>'
            );
        }

        $sweepFlag = $topFilled ? 1 : 0;

        return new HtmlString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
            .'<circle cx="12" cy="12" r="9" />'
            ."<path d=\"M3 12a9 9 0 0 {$sweepFlag} 18 0z\" fill=\"currentColor\" stroke=\"none\" />"
            .'</svg>'
        );
    }

    private function toggleDay(int $candidateId, int $isoWeekday): void
    {
        if (! empty($this->selectedDays[$candidateId][$isoWeekday])) {
            unset($this->selectedDays[$candidateId][$isoWeekday]);

            return;
        }

        $this->selectedDays[$candidateId][$isoWeekday] = true;
    }

    private function isDaySelected(int $candidateId, int $isoWeekday): bool
    {
        return ! empty($this->selectedDays[$candidateId][$isoWeekday]);
    }

    /** @return array<int, string> */
    private function selectedDatesFor(int $candidateId, CarbonImmutable $weekStart): array
    {
        return collect($this->selectedDays[$candidateId] ?? [])
            ->filter()
            ->keys()
            ->map(fn (int $isoWeekday): string => $weekStart->copy()->addDays($isoWeekday - 1)->toDateString())
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Carries the candidate's AM/PM availability through to the booking
     * form for each selected date, so picking a day marked "Available AM"
     * (say) doesn't silently default to a full-day booking.
     *
     * @return array<string, string>
     */
    private function selectedPeriodsFor(HealthcareCandidate $record, CarbonImmutable $weekStart): array
    {
        return collect($this->selectedDays[$record->id] ?? [])
            ->filter()
            ->keys()
            ->mapWithKeys(function (int $isoWeekday) use ($record, $weekStart): array {
                $date = $weekStart->copy()->addDays($isoWeekday - 1);

                return [$date->toDateString() => $this->bookingPeriodFor($record, $date)];
            })
            ->all();
    }

    private function bookingPeriodFor(HealthcareCandidate $record, CarbonImmutable $date): string
    {
        return match ($this->availabilityStatusFor($record, $date)) {
            CandidateAvailabilityStatus::AvailableAm->value => BookingDayPeriod::Am->value,
            CandidateAvailabilityStatus::AvailablePm->value => BookingDayPeriod::Pm->value,
            default => BookingDayPeriod::FullDay->value,
        };
    }
}
