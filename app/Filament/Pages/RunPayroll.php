<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Filament\Concerns\HasTimesheetPeriodNavigation;
use App\Filament\Resources\Bookings\BookingResource;
use App\Jobs\SendPayrollConfirmationEmail;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Company;
use App\Services\Booking\TimesheetPeriod;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class RunPayroll extends Page implements HasTable
{
    use HasTimesheetPeriodNavigation;
    use InteractsWithTable;

    protected string $view = 'filament.pages.run-payroll';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Run Payroll';

    protected static \UnitEnum|string|null $navigationGroup = 'Admin';

    public static function canAccess(): bool
    {
        // Impersonation logs the site_admin in as the target company's actual
        // admin user, so this excludes their own site_admin account without
        // needing to check the impersonation session state directly.
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function mount(): void
    {
        $this->periodStart = TimesheetPeriod::current($this->periodCompany())['start']->toDateString();
    }

    public function getHeading(): ?string
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        $period = $this->currentPeriod();

        return $period['start']->format('jS M Y').' - '.$period['end']->format('jS M Y');
    }

    public function table(Table $table): Table
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
            ->headerActions([
                ...$this->periodNavigationActions(),
                Action::make('confirm')
                    ->label(fn (): string => $this->hasAnyConfirmationBeenSent() ? 'Resend' : 'Confirm')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('This will email every client with bookings this period, asking them to review and approve or dispute their timesheet.')
                    ->disabled(fn (): bool => ! $this->hasUpcomingBookings())
                    ->tooltip(fn (): ?string => $this->hasUpcomingBookings()
                        ? null
                        : 'All bookings for this period have already been sent.')
                    ->action(function (): void {
                        $period = $this->currentPeriod();
                        $clientIds = $this->periodClientIds();

                        foreach ($clientIds as $clientId) {
                            SendPayrollConfirmationEmail::dispatch(Client::findOrFail($clientId), $period['start']->toDateString());
                        }

                        Notification::make()
                            ->title($clientIds->count().' payroll confirmation email(s) queued')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('date')
            ->paginated(false)
            ->emptyStateHeading('No bookings scheduled for this period');
    }

    protected function periodCompany(): Company
    {
        return Auth::user()->company;
    }

    private function dayPeriodsQuery()
    {
        $period = $this->currentPeriod();

        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->visibleToCurrentUser())
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->whereNull('cancelled_at')
            ->with([
                'booking.client' => fn ($query) => $query->withTrashed(),
                'booking.candidate' => fn ($query) => $query->withTrashed(),
                'booking.jobTitle',
            ]);
    }

    /** @return Collection<int, int> */
    private function periodClientIds()
    {
        $period = $this->currentPeriod();

        return Booking::query()
            ->visibleToCurrentUser()
            ->whereHas('dayPeriods', function ($query) use ($period): void {
                $query->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
                    ->whereNull('cancelled_at');
            })
            ->pluck('client_id')
            ->unique();
    }

    private function hasAnyConfirmationBeenSent(): bool
    {
        $period = $this->currentPeriod();

        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->visibleToCurrentUser())
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->whereNull('cancelled_at')
            ->whereNotNull('payroll_confirmation_sent_at')
            ->exists();
    }

    private function hasUpcomingBookings(): bool
    {
        $period = $this->currentPeriod();

        return Booking::query()
            ->visibleToCurrentUser()
            ->where('status', BookingStatus::Upcoming)
            ->whereHas('dayPeriods', function ($query) use ($period): void {
                $query->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
                    ->whereNull('cancelled_at');
            })
            ->exists();
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
