<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\BookingDay;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
    /** @param  array<int, Action>  $headerActions */
    protected function configurePayrollTable(Table $table, array $headerActions = []): Table
    {
        return $table
            ->query(fn () => $this->dayPeriodsQuery())
            ->recordUrl(fn (BookingDay $record): string => BookingResource::getUrl('edit', ['record' => $record->booking]))
            ->groups([
                Group::make('booking.client_id')
                    ->label('Client')
                    ->getTitleFromRecordUsing(fn (BookingDay $record): string => $this->clientLabel($record))
                    ->collapsible(),
            ])
            ->defaultGroup('booking.client_id')
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
                    ->getStateUsing(fn (BookingDay $record): string => $record->payrollStatus())
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'disputed' => 'danger',
                        'sent' => 'info',
                        default => 'gray',
                    }),
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
