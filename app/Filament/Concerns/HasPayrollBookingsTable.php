<?php

namespace App\Filament\Concerns;

use App\Enums\PayrollStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\BookingDay;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * The read side of the payroll period table — the query, grouping, and
 * columns showing each booking day's candidate/job title/date/session/
 * approval status for the current period — shared between RunPayroll
 * (which adds the ability to send confirmations) and ViewPayroll (a
 * consultant-facing, read-only view of the same data for their own
 * bookings). Each page supplies its own header actions, since that's
 * exactly where the two differ.
 */
trait HasPayrollBookingsTable
{
    /** @var array<int, array{total: int, approved: int, disputed: int, awaiting: int}> Memoized per client_id — see clientPeriodStats(). */
    private array $clientPeriodStatsCache = [];

    /**
     * @param  array<int, Action>  $headerActions
     * @param  bool  $collapseGroupsByDefault  RunPayroll opts into this (its client groups can be numerous,
     *                                         and the red/green status badge below means a collapsed row
     *                                         still says everything needed at a glance); ViewPayroll leaves it
     *                                         false, since that page's whole point is a chase list of days
     *                                         still needing approval already sitting visible on screen.
     */
    protected function configurePayrollTable(Table $table, array $headerActions = [], bool $collapseGroupsByDefault = false): Table
    {
        return $table
            ->query(fn () => $this->dayPeriodsQuery())
            ->recordUrl(fn (BookingDay $record): string => BookingResource::getUrl('edit', ['record' => $record->booking]))
            ->groups([
                Group::make('booking.client_id')
                    ->label('Client')
                    ->getTitleFromRecordUsing(fn (BookingDay $record): Htmlable => $this->clientGroupTitle($record))
                    ->getDescriptionFromRecordUsing(fn (BookingDay $record): ?Htmlable => $this->clientGroupDescription($record))
                    ->collapsible(),
            ])
            ->defaultGroup('booking.client_id')
            ->collapsedGroupsByDefault($collapseGroupsByDefault)
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('candidate_name')
                    ->label('Candidate')
                    ->getStateUsing(fn (BookingDay $record): string => $this->candidateLabel($record)),
                TextColumn::make('booking.jobTitle.name')
                    ->label('Job Title')
                    ->placeholder('—'),
                TextColumn::make('date')
                    ->label('Date')
                    ->date('D jS M Y')
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Session')
                    ->formatStateUsing(fn ($state): string => $state->label()),
                TextColumn::make('payroll_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (BookingDay $record): PayrollStatus => $record->payrollStatus())
                    ->formatStateUsing(fn (PayrollStatus $state): string => $state->label())
                    ->color(fn (PayrollStatus $state): string => $state->color()),
            ])
            ->headerActions($headerActions)
            ->defaultSort('date')
            ->paginated(false)
            ->emptyStateHeading('No bookings scheduled for this period');
    }

    private function dayPeriodsQuery()
    {
        $period = $this->currentPeriod();

        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $this->scopePayrollBookingsQuery($query)->excludingRequests())
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->whereNull('cancelled_at')
            ->with([
                'booking.client' => fn ($query) => $query->withTrashed(),
                'booking.candidate' => fn ($query) => $query->withTrashed(),
                'booking.jobTitle',
                'approvedBy:id,name',
            ]);
    }

    /**
     * Which bookings this table is allowed to show — RunPayroll (admin and
     * compliance) uses the normal visibleToCurrentUser() scope (everyone at
     * the company), while ViewPayroll overrides this to always scope to the
     * viewer's own bookings regardless of role, admin included.
     */
    protected function scopePayrollBookingsQuery(Builder $query): Builder
    {
        return $query->visibleToCurrentUser();
    }

    private function clientLabel(BookingDay $record): string
    {
        $client = $record->booking?->client;

        if (! $client) {
            return 'Unknown client';
        }

        return $client->trashed() ? "{$client->name} (deleted)" : $client->name;
    }

    /**
     * A warning-triangle/check-circle icon next to the client's name — a
     * saturated colour that's actually visible at a glance, replacing the
     * previous pale red/green group-header background tint that could wash
     * out the row text it sat behind. The icon is aria-hidden with an
     * adjacent sr-only label carrying the same meaning for screen readers.
     */
    private function clientGroupTitle(BookingDay $record): Htmlable
    {
        $clientId = $record->booking?->client_id;
        $needsAttention = $clientId === null || $this->clientHasUnapprovedDays($clientId);

        return new HtmlString(Blade::render(
            <<<'BLADE'
                <x-filament::icon
                    :icon="$icon"
                    :class="$class"
                    aria-hidden="true"
                /><span class="sr-only">{{ $srLabel }}</span> {{ $name }}
                BLADE,
            [
                'icon' => $needsAttention ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle',
                'class' => $needsAttention
                    ? 'inline h-4 w-4 shrink-0 align-text-bottom text-red-600 dark:text-red-400'
                    : 'inline h-4 w-4 shrink-0 align-text-bottom text-green-600 dark:text-green-400',
                'srLabel' => $needsAttention ? 'Bookings to confirm' : 'Fully approved',
                'name' => $this->clientLabel($record),
            ]
        ));
    }

    /**
     * The visible line under the client's name — how many days this client
     * has this period and, when relevant, how many of those are disputed or
     * still awaiting a response, so the consultant can gauge the size of
     * what's outstanding without expanding the (collapsed-by-default) group.
     */
    private function clientGroupDescription(BookingDay $record): ?Htmlable
    {
        $clientId = $record->booking?->client_id;

        if ($clientId === null) {
            return null;
        }

        $stats = $this->clientPeriodStats($clientId);
        $dayWord = $stats['total'] === 1 ? 'day' : 'days';
        $parts = ["{$stats['total']} {$dayWord} this period"];

        if ($stats['disputed'] > 0) {
            $parts[] = "{$stats['disputed']} disputed";
        }

        if ($stats['awaiting'] > 0) {
            $parts[] = "{$stats['awaiting']} awaiting response";
        }

        if ($stats['disputed'] === 0 && $stats['awaiting'] === 0) {
            $parts[] = 'all approved';
        }

        return new HtmlString(e(implode(' · ', $parts)));
    }

    /**
     * Whether this client has at least one day this period that isn't
     * cleanly Approved.
     */
    private function clientHasUnapprovedDays(int $clientId): bool
    {
        $stats = $this->clientPeriodStats($clientId);

        return $stats['approved'] !== $stats['total'];
    }

    /**
     * Day counts for a client's current period, broken down by payroll
     * status — memoized per client_id so a period with many rows per client
     * doesn't re-run the underlying query for every group header re-render.
     *
     * @return array{total: int, approved: int, disputed: int, awaiting: int}
     */
    private function clientPeriodStats(int $clientId): array
    {
        if (array_key_exists($clientId, $this->clientPeriodStatsCache)) {
            return $this->clientPeriodStatsCache[$clientId];
        }

        $period = $this->currentPeriod();

        $statuses = BookingDay::query()
            ->whereHas('booking', fn ($query) => $this->scopePayrollBookingsQuery($query)
                ->excludingRequests()
                ->where('client_id', $clientId))
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->whereNull('cancelled_at')
            ->get()
            ->map(fn (BookingDay $day): PayrollStatus => $day->payrollStatus());

        $approved = $statuses->filter(fn (PayrollStatus $status): bool => $status === PayrollStatus::Approved)->count();
        $disputed = $statuses->filter(fn (PayrollStatus $status): bool => $status === PayrollStatus::Disputed)->count();

        return $this->clientPeriodStatsCache[$clientId] = [
            'total' => $statuses->count(),
            'approved' => $approved,
            'disputed' => $disputed,
            'awaiting' => $statuses->count() - $approved - $disputed,
        ];
    }

    private function candidateLabel(BookingDay $record): string
    {
        $candidate = $record->booking?->candidate;

        if (! $candidate) {
            return 'Unknown candidate';
        }

        $name = trim("{$candidate->first_name} {$candidate->last_name}");

        return $candidate->trashed() ? "{$name} (deleted)" : $name;
    }
}
