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
    /** @var array<int, bool> Memoized per client_id — see clientHasUnapprovedDays(). */
    private array $clientHasUnapprovedDaysCache = [];

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
                    ->getTitleFromRecordUsing(fn (BookingDay $record): string => $this->clientLabel($record))
                    ->getDescriptionFromRecordUsing(fn (BookingDay $record): Htmlable => $this->clientApprovalStatusMarker($record))
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
     * Which bookings this table is allowed to show — RunPayroll (admin-only)
     * uses the normal visibleToCurrentUser() scope (everyone at the
     * company), while ViewPayroll overrides this to always scope to the
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
     * A visually-hidden marker (no visible badge text — just the group
     * header's red/green tint) carrying a data-payroll-group-status
     * attribute so CSS (see run-payroll.blade.php) can colour the whole
     * group header row to match, since Filament's table Group has no
     * colour/class API of its own. Still readable by screen readers.
     */
    private function clientApprovalStatusMarker(BookingDay $record): Htmlable
    {
        $clientId = $record->booking?->client_id;

        if ($clientId === null || $this->clientHasUnapprovedDays($clientId)) {
            return new HtmlString(
                '<span data-payroll-group-status="awaiting" class="sr-only">Bookings to confirm</span>'
            );
        }

        return new HtmlString(
            '<span data-payroll-group-status="approved" class="sr-only">Fully approved</span>'
        );
    }

    /**
     * Whether this client has at least one day this period that isn't
     * cleanly Approved — memoized per client_id so a period with many rows
     * per client doesn't re-run this for every group header re-render.
     */
    private function clientHasUnapprovedDays(int $clientId): bool
    {
        if (array_key_exists($clientId, $this->clientHasUnapprovedDaysCache)) {
            return $this->clientHasUnapprovedDaysCache[$clientId];
        }

        $period = $this->currentPeriod();

        return $this->clientHasUnapprovedDaysCache[$clientId] = BookingDay::query()
            ->whereHas('booking', fn ($query) => $this->scopePayrollBookingsQuery($query)
                ->excludingRequests()
                ->where('client_id', $clientId))
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->whereNull('cancelled_at')
            ->get()
            ->contains(fn (BookingDay $day): bool => $day->payrollStatus()->isAwaitingApproval());
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
