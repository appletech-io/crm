<?php

namespace App\Filament\Resources\EducationCandidates\Pages;

use App\Actions\Candidates\CandidateCreated;
use App\Enums\BookingDayPeriod;
use App\Enums\CandidateAvailabilityStatus;
use App\Enums\EmailTemplateAudience;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Support\AddToCandidatePoolAction;
use App\Filament\Support\CandidateSummaryAction;
use App\Filament\Support\SendCustomEmailAction;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\CandidateAvailability;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Services\Education\CandidateSearchService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;

class ListEducationCandidates extends ListRecords implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = EducationCandidateResource::class;

    protected string $view = 'filament.resources.education-candidates.pages.list-education-candidates';

    public string $activeSection = 'search';

    public ?array $data = [];

    public ?float $searchLat = null;

    public ?float $searchLng = null;

    /** @var array<int, array<int, bool>> */
    public array $selectedDays = [];

    /**
     * Per-candidate, per-day availability statuses staged from an unknown
     * ("?") cell's hover dropdown but not yet saved — keyed the same way as
     * $selectedDays. Committed to the database, and cleared, by
     * saveStagedAvailability() (the row's "Save" action).
     *
     * @var array<int, array<int, string>>
     */
    public array $pendingAvailability = [];

    public string $weekStart;

    public function mount(): void
    {
        parent::mount();

        $this->weekStart = now()->startOfWeek(CarbonImmutable::MONDAY)->toDateString();

        $this->form->fill(['radius_miles' => 10]);
        $this->search();
    }

    public function updatedActiveSection(): void
    {
        $this->resetTable();
    }

    public function goToPreviousWeek(): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->subWeek()->toDateString();
        $this->resetTable();
    }

    public function goToNextWeek(): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeek()->toDateString();
        $this->resetTable();
    }

    public function goToCurrentWeek(): void
    {
        $this->weekStart = now()->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkUploadCvs')
                ->label('Bulk Upload CVs')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('bulk-upload-cvs')),
            CreateAction::make()
                ->label('New Candidate')
                ->modalHeading('Add EducationCandidate')
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
                        ->unique(EducationCandidate::class, 'email'),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data['consultant_id'] = auth()->id();

                    return $data;
                })
                ->after(function (EducationCandidate $record) {
                    CandidateCreated::run($record);

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

    /**
     * The "All Candidates" tab always uses the resource's own default table
     * (EducationCandidatesTable — a bare, unfiltered company-wide list with
     * its own native filters). Only the dedicated "Search" tab uses this
     * page's custom booking-search form/table below.
     */
    public function table(Table $table): Table
    {
        if ($this->activeSection !== 'search') {
            return $table;
        }

        return $this->configureSearchTable($table);
    }

    protected function configureSearchTable(Table $table): Table
    {
        $weekStart = CarbonImmutable::parse($this->weekStart);

        // Shared with the "name" column below (see ->action() there) so
        // clicking a candidate's name mounts this exact same registered
        // action, rather than the whole row linking through to the edit
        // page — quick view only, never a direct edit-page link from here.
        $quickViewAction = CandidateSummaryAction::make();

        return $table
            ->query(fn (): Builder => app(CandidateSearchService::class)
                ->search([
                    'name' => $this->data['name'] ?? null,
                    'email' => $this->data['email'] ?? null,
                    'skill_ids' => $this->data['skill_ids'] ?? null,
                    'days' => $this->data['days'] ?? null,
                    'week_start' => $weekStart->toDateString(),
                    'lat' => $this->searchLat,
                    'lng' => $this->searchLng,
                    'radius_miles' => $this->data['radius_miles'] ?? null,
                    'pool_ids' => $this->data['pool_ids'] ?? null,
                ], restrictToOwnLiveCandidates: true)
                ->with([
                    'bookings' => fn ($query) => $query
                        ->whereHas('dayPeriods', fn ($q) => $q
                            ->whereDate('date', '>=', $weekStart->toDateString())
                            ->whereDate('date', '<=', $weekStart->copy()->addDays(4)->toDateString())
                            ->whereNull('cancelled_at'))
                        ->with([
                            'dayPeriods' => fn ($q) => $q
                                ->whereDate('date', '>=', $weekStart->toDateString())
                                ->whereDate('date', '<=', $weekStart->copy()->addDays(4)->toDateString())
                                ->whereNull('cancelled_at'),
                            'client:id,name',
                        ]),
                    'availabilities' => fn ($query) => $query
                        ->whereDate('date', '>=', $weekStart->toDateString())
                        ->whereDate('date', '<=', $weekStart->copy()->addDays(4)->toDateString()),
                ]))
            // Filament's ListRecords otherwise auto-wires a click-to-edit
            // fallback onto rows whenever the resource has an edit page,
            // even with no explicit recordUrl() call — that conflicted with
            // this grid's own per-cell click interactions, so it's disabled
            // here and the candidate's name is the only click-through,
            // opening the quick view instead (see the "name" column below).
            ->recordUrl(null)
            ->filters([])
            ->recordActions([
                // Registered but never rendered as its own row button — kept
                // here purely so its name is resolvable when the "name"
                // column's own ->action() (see the "name" column below)
                // mounts it by name. The visible trigger is the candidate's
                // name text, not a separate icon.
                $quickViewAction->hidden(),
                ActionGroup::make([
                    Action::make('book')
                        ->label('Book')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->color('success')
                        ->visible(fn (EducationCandidate $record): bool => filled($this->selectedDatesFor($record->id, $weekStart)))
                        ->url(fn (EducationCandidate $record): string => BookingResource::getUrl('create', [
                            'candidate_id' => $record->id,
                            'client_id' => $this->data['client_id'] ?? null,
                            'dates' => $this->selectedDatesFor($record->id, $weekStart),
                            'periods' => $this->selectedPeriodsFor($record, $weekStart),
                        ])),
                    Action::make('setWeekAvailability')
                        ->label('Set Week')
                        ->icon('heroicon-o-pencil-square')
                        ->color('gray')
                        ->modalHeading('Set availability for this week')
                        ->modalSubmitActionLabel('Save')
                        ->schema([
                            Select::make('status')
                                ->label('Availability')
                                ->options([
                                    CandidateAvailabilityStatus::Available->value => 'Full',
                                    CandidateAvailabilityStatus::AvailableAm->value => 'AM',
                                    CandidateAvailabilityStatus::AvailablePm->value => 'PM',
                                ])
                                ->required(),
                        ])
                        ->action(fn (array $data, EducationCandidate $record) => $this->applyStatusToUnknownWeekDays($record->id, $data['status'])),
                    Action::make('saveWeekAvailability')
                        ->label('Save')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn (EducationCandidate $record): bool => filled($this->pendingAvailability[$record->id] ?? null))
                        ->action(fn (EducationCandidate $record) => $this->saveStagedAvailability($record->id)),
                ])
                    // A fixed "..." trigger rather than Book appearing/
                    // disappearing inline as days get toggled for booking —
                    // that used to shift the whole row's width while quickly
                    // clicking day cells to select them. "Set Week" always
                    // being visible also keeps this trigger itself always on
                    // screen, even with nothing currently selected/staged.
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->tooltip('Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SendCustomEmailAction::bulk(EmailTemplateAudience::Candidate),
                    AddToCandidatePoolAction::bulk(EducationCandidate::class),
                ]),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->getStateUsing(fn (EducationCandidate $record): string => trim("{$record->first_name} {$record->last_name}"))
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->tooltip('Quick view')
                    ->action($quickViewAction),
                TextColumn::make('phone')
                    ->placeholder('—'),
                TextColumn::make('average_rating')
                    ->label('Rating')
                    ->badge()
                    ->formatStateUsing(fn (?float $state, EducationCandidate $record): string => $state !== null
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
                    ->getStateUsing(fn (EducationCandidate $record): string => $this->availableDayCount($record, $weekStart).'/5 available')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $this->orderByAvailability($query, $direction, $weekStart))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distance')
                    ->label('Distance')
                    ->getStateUsing(function (EducationCandidate $record): ?string {
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
                ColumnGroup::make($this->weekNavigationLabel($weekStart), $this->dayColumns($weekStart)),
            ])
            ->paginated([10, 25, 50, 100, 250, 500, 'all'])
            ->emptyStateHeading('No candidates match your search');
    }

    /**
     * The spanning header above the day columns (see ColumnGroup usage in
     * configureSearchTable()) — previous/next week arrows either side of a
     * "W/C dd/mm" label, replacing the old page-header week actions so the
     * controls sit directly against the days they navigate. Clicking the
     * label itself jumps back to the current week.
     */
    private function weekNavigationLabel(CarbonImmutable $weekStart): Htmlable
    {
        return new HtmlString(
            '<div class="flex items-center justify-center gap-1.5">'
            .'<button type="button" wire:click="goToPreviousWeek" aria-label="Previous week" class="rounded p-0.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200">'
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1 0 1.06L9.06 10l3.73 3.71a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" /></svg>'
            .'</button>'
            .'<button type="button" wire:click="goToCurrentWeek" title="Jump to current week" class="whitespace-nowrap text-xs font-semibold text-gray-700 hover:underline dark:text-gray-200">'
            .'W/C '.$weekStart->format('d/m')
            .'</button>'
            .'<button type="button" wire:click="goToNextWeek" aria-label="Next week" class="rounded p-0.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200">'
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 0-1.06L10.94 10 7.21 6.29a.75.75 0 1 1 1.06-1.06l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" /></svg>'
            .'</button>'
            .'</div>'
        );
    }

    /**
     * Each day is a fully custom ViewColumn rather than a plain IconColumn
     * so that an unknown ("?") cell can carry a hover dropdown for quickly
     * setting Full/AM/PM availability, alongside the existing click-to-select
     * behaviour — the two can't coexist inside a single native Filament
     * column action, since that would nest interactive elements inside the
     * column's own click wrapper. See partials/availability-day-cell.
     *
     * @return array<int, ViewColumn>
     */
    private function dayColumns(CarbonImmutable $weekStart): array
    {
        return collect(CandidateSearchService::WEEKDAYS)
            ->map(function (int $isoWeekday) use ($weekStart): ViewColumn {
                $date = $weekStart->copy()->addDays($isoWeekday - 1);

                return ViewColumn::make("day_{$isoWeekday}")
                    ->label($date->format('D'))
                    ->alignCenter()
                    ->view('filament.resources.education-candidates.pages.partials.availability-day-cell')
                    ->getStateUsing(function (EducationCandidate $record) use ($date, $isoWeekday): array {
                        $status = $this->availabilityStatusFor($record, $date);

                        return [
                            'candidateId' => $record->id,
                            'isoWeekday' => $isoWeekday,
                            'date' => $date->toDateString(),
                            'status' => $status,
                            'isSelectable' => $this->isSelectableStatus($status),
                            'icon' => $this->dayIcon(
                                $status,
                                $this->isDaySelected($record->id, $isoWeekday),
                                $this->bookedCoverageFor($record, $date),
                            ),
                            'colorClasses' => $this->dayColor($status),
                            'tooltip' => $this->dayTooltip($status, $this->bookedDetailsFor($record, $date)),
                            // Staged but not yet saved — see stageAvailability()/
                            // saveStagedAvailability() and the "Save" row action.
                            'pendingStatus' => $this->pendingAvailability[$record->id][$isoWeekday] ?? null,
                        ];
                    });
            })
            ->all();
    }

    /**
     * The existing "click a day to select it for booking" behaviour,
     * unchanged in effect from before the day columns became custom views —
     * just invoked directly via wire:click from the cell partial now, rather
     * than through a Filament column action. The status is re-derived here
     * rather than trusted from the client, so a stale/tampered cell can't
     * select a day that's actually booked or marked Not Available.
     */
    public function handleDayClick(int $candidateId, int $isoWeekday): void
    {
        $candidate = EducationCandidate::query()->find($candidateId);

        if (! $candidate) {
            return;
        }

        $date = CarbonImmutable::parse($this->weekStart)->addDays($isoWeekday - 1);

        if (! $this->isSelectableStatus($this->availabilityStatusFor($candidate, $date))) {
            return;
        }

        $this->toggleDay($candidateId, $isoWeekday);
    }

    /**
     * Quickly sets a candidate's availability for a single day from the
     * hover dropdown on an unknown ("?") cell in the weekly grid — mirrors
     * HasCandidateAvailabilityCalendar::setAvailabilityStatus(), scoped to
     * one date and restricted to the three settable statuses offered there
     * (Full/AM/PM aren't the whole enum: Not Available/clear aren't offered
     * by this quick-set control).
     */
    public function setQuickAvailability(int $candidateId, string $date, string $status): void
    {
        if (! in_array($status, [
            CandidateAvailabilityStatus::Available->value,
            CandidateAvailabilityStatus::AvailableAm->value,
            CandidateAvailabilityStatus::AvailablePm->value,
        ], true)) {
            return;
        }

        $candidate = EducationCandidate::query()->find($candidateId);

        if (! $candidate) {
            return;
        }

        $isBooked = BookingDay::query()
            ->whereHas('booking', fn (Builder $query) => $query
                ->where('candidate_id', $candidateId)
                ->where('candidate_type', EducationCandidate::class))
            ->whereDate('date', $date)
            ->whereNull('cancelled_at')
            ->exists();

        if ($isBooked) {
            return;
        }

        $existing = $candidate->availabilities()->whereDate('date', $date)->first();

        if ($existing) {
            $existing->update(['status' => $status]);
        } else {
            $candidate->availabilities()->create(['date' => $date, 'status' => $status]);
        }
    }

    /**
     * Stages a status for one day from the hover dropdown on an unknown
     * ("?") cell — nothing is written to the database yet. Clicking the same
     * status again for the same day un-stages it. The row's "Save" action
     * (saveStagedAvailability()) is what actually persists these.
     */
    public function stageAvailability(int $candidateId, int $isoWeekday, string $status): void
    {
        if (! in_array($status, [
            CandidateAvailabilityStatus::Available->value,
            CandidateAvailabilityStatus::AvailableAm->value,
            CandidateAvailabilityStatus::AvailablePm->value,
        ], true)) {
            return;
        }

        if (($this->pendingAvailability[$candidateId][$isoWeekday] ?? null) === $status) {
            unset($this->pendingAvailability[$candidateId][$isoWeekday]);

            return;
        }

        $this->pendingAvailability[$candidateId][$isoWeekday] = $status;
    }

    /**
     * The row's "Save" action — persists every day staged via
     * stageAvailability() for this candidate (each keeping whichever status
     * it was individually set to), reusing setQuickAvailability()'s own
     * guards (booked days, valid statuses) for each one, then clears the
     * staged selection for this row.
     */
    public function saveStagedAvailability(int $candidateId): void
    {
        $staged = $this->pendingAvailability[$candidateId] ?? [];

        if (blank($staged)) {
            return;
        }

        $weekStart = CarbonImmutable::parse($this->weekStart);

        foreach ($staged as $isoWeekday => $status) {
            $date = $weekStart->copy()->addDays($isoWeekday - 1);

            $this->setQuickAvailability($candidateId, $date->toDateString(), $status);
        }

        unset($this->pendingAvailability[$candidateId]);

        Notification::make()
            ->success()
            ->title('Availability saved')
            ->send();
    }

    /**
     * The "Set Week" row action — a quicker bulk alternative to staging each
     * day individually: applies one status to every day in the currently
     * displayed week that has no availability recorded yet. Days already
     * marked Available/AM/PM/Not Available or actually booked are left
     * untouched, reusing setQuickAvailability()'s own guards for each day.
     */
    public function applyStatusToUnknownWeekDays(int $candidateId, string $status): void
    {
        if (! in_array($status, [
            CandidateAvailabilityStatus::Available->value,
            CandidateAvailabilityStatus::AvailableAm->value,
            CandidateAvailabilityStatus::AvailablePm->value,
        ], true)) {
            return;
        }

        $candidate = EducationCandidate::query()->find($candidateId);

        if (! $candidate) {
            return;
        }

        $weekStart = CarbonImmutable::parse($this->weekStart);

        foreach (CandidateSearchService::WEEKDAYS as $isoWeekday) {
            $date = $weekStart->copy()->addDays($isoWeekday - 1);

            if ($this->availabilityStatusFor($candidate, $date) === null) {
                $this->setQuickAvailability($candidateId, $date->toDateString(), $status);
            }
        }
    }

    /**
     * The candidate's availability status for this date, from the
     * "availabilities" and "bookings.dayPeriods" relations already eager
     * loaded by configureSearchTable() — an active booking always wins over
     * whatever's stored, mirroring CandidateMonthlyAvailability. A null
     * result means no availability has been recorded for that day at all.
     */
    private function availabilityStatusFor(EducationCandidate $record, CarbonImmutable $date): ?string
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
    private function availableDayCount(EducationCandidate $record, CarbonImmutable $weekStart): int
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
                - (SELECT COUNT(DISTINCT DATE(bd.date)) FROM booking_days bd INNER JOIN bookings b ON b.id = bd.booking_id WHERE b.candidate_type = ? AND b.candidate_id = education_candidates.id AND DATE(bd.date) BETWEEN ? AND ? AND bd.cancelled_at IS NULL)
                - (SELECT COUNT(*) FROM candidate_availabilities ca WHERE ca.candidate_type = ? AND ca.candidate_id = education_candidates.id AND DATE(ca.date) BETWEEN ? AND ? AND ca.status = ?)
            ) '.$direction,
            [
                EducationCandidate::class, $weekStart->toDateString(), $weekEnd->toDateString(),
                EducationCandidate::class, $weekStart->toDateString(), $weekEnd->toDateString(), CandidateAvailabilityStatus::NotAvailable->value,
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
    private function bookedCoverageFor(EducationCandidate $record, CarbonImmutable $date): string
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

    /**
     * Tailwind text-color classes for the day icon — plain utility classes
     * rather than Filament's `->color()` semantic tokens, since the day
     * columns are now custom ViewColumns rendered outside Filament's own
     * icon-column markup (see dayColumns()).
     */
    private function dayColor(?string $status): string
    {
        return match ($status) {
            CandidateAvailabilityStatus::Available->value,
            CandidateAvailabilityStatus::AvailableAm->value,
            CandidateAvailabilityStatus::AvailablePm->value => 'text-green-600 dark:text-green-400',
            CandidateAvailabilityStatus::Booked->value => 'text-blue-600 dark:text-blue-400',
            CandidateAvailabilityStatus::NotAvailable->value => 'text-red-600 dark:text-red-400',
            default => 'text-amber-600 dark:text-amber-400',
        };
    }

    /**
     * @param  array<int, array{client: ?string, period: BookingDayPeriod, pay_rate: ?float, charge_rate: ?float}>  $bookedDetails
     */
    private function dayTooltip(?string $status, array $bookedDetails): string
    {
        return match ($status) {
            CandidateAvailabilityStatus::Available->value => 'Available — click to select this day for booking',
            CandidateAvailabilityStatus::AvailableAm->value => 'Available AM — click to select this day for booking',
            CandidateAvailabilityStatus::AvailablePm->value => 'Available PM — click to select this day for booking',
            CandidateAvailabilityStatus::Booked->value => $this->bookedTooltip($bookedDetails),
            CandidateAvailabilityStatus::NotAvailable->value => 'Marked as not available this day',
            default => 'Availability not set for this day — click to select for booking',
        };
    }

    /**
     * The client, pay rate, and charge rate for each booking covering this
     * day — usually one, but a split AM/PM day can have two separate
     * bookings (different clients/rates), so each is labelled with its
     * period when there's more than one.
     *
     * @param  array<int, array{client: ?string, period: BookingDayPeriod, pay_rate: ?float, charge_rate: ?float}>  $bookedDetails
     */
    private function bookedTooltip(array $bookedDetails): string
    {
        if ($bookedDetails === []) {
            return 'Already has a booking this day';
        }

        $showPeriodLabel = count($bookedDetails) > 1;

        return collect($bookedDetails)
            ->map(function (array $detail) use ($showPeriodLabel): string {
                $prefix = $showPeriodLabel ? "{$detail['period']->label()}: " : '';
                $client = $detail['client'] ?? 'Unknown client';
                $pay = $detail['pay_rate'] !== null ? '£'.number_format($detail['pay_rate'], 2) : '—';
                $charge = $detail['charge_rate'] !== null ? '£'.number_format($detail['charge_rate'], 2) : '—';

                return "{$prefix}{$client} — Pay {$pay} / Charge {$charge}";
            })
            ->implode(' | ');
    }

    /**
     * @return array<int, array{client: ?string, period: BookingDayPeriod, pay_rate: ?float, charge_rate: ?float}>
     */
    private function bookedDetailsFor(EducationCandidate $record, CarbonImmutable $date): array
    {
        return $record->bookings
            ->flatMap(fn (Booking $booking) => $booking->dayPeriods
                ->filter(fn (BookingDay $dayPeriod): bool => $dayPeriod->date->isSameDay($date))
                ->map(fn (BookingDay $dayPeriod): array => [
                    'client' => $booking->client?->name,
                    'period' => $dayPeriod->period,
                    'pay_rate' => $dayPeriod->payRate(),
                    'charge_rate' => $dayPeriod->chargeRate(),
                ]))
            ->all();
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
    private function selectedPeriodsFor(EducationCandidate $record, CarbonImmutable $weekStart): array
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

    private function bookingPeriodFor(EducationCandidate $record, CarbonImmutable $date): string
    {
        return match ($this->availabilityStatusFor($record, $date)) {
            CandidateAvailabilityStatus::AvailableAm->value => BookingDayPeriod::Am->value,
            CandidateAvailabilityStatus::AvailablePm->value => BookingDayPeriod::Pm->value,
            default => BookingDayPeriod::FullDay->value,
        };
    }
}
