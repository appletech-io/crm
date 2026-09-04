<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPayrollBookingsTable;
use App\Filament\Concerns\HasTimesheetPeriodNavigation;
use App\Filament\Support\ExportPayrollCsvAction;
use App\Jobs\SendPayrollConfirmationEmail;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class RunPayroll extends Page implements HasTable
{
    use HasPayrollBookingsTable;
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
        $this->goToCurrentPeriod();
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
        return $this->configurePayrollTable($table, [
            ...$this->periodNavigationActions(),
            // Shown for every company, even one with a payroll provider
            // (e.g. Evertime) already configured — useful as a manual
            // backup/cross-check alongside the automatic sync.
            ExportPayrollCsvAction::header(
                fn () => $this->dayPeriodsQuery()->get(),
                fn () => $this->currentPeriod(),
            ),
            Action::make('confirm')
                ->label(fn (): string => $this->hasAnyConfirmationBeenSent() ? 'Resend' : 'Confirm')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('This will email every client with a booking this period that has not yet been sent a confirmation.')
                ->disabled(fn (): bool => $this->unsentClientIds()->isEmpty())
                ->tooltip(fn (): ?string => $this->unsentClientIds()->isEmpty()
                    ? 'All bookings for this period have already been sent.'
                    : null)
                ->action(function (): void {
                    $period = $this->currentPeriod();
                    $clientIds = $this->unsentClientIds();

                    foreach ($clientIds as $clientId) {
                        SendPayrollConfirmationEmail::dispatch(Client::findOrFail($clientId), $period['start']->toDateString());
                    }

                    Notification::make()
                        ->title($clientIds->count().' payroll confirmation email(s) queued')
                        ->success()
                        ->send();
                }),
            Action::make('remind')
                ->label('Send Reminders')
                ->icon('heroicon-o-bell-alert')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This will email every client who has not yet approved or disputed their timesheet for this period, asking them to do so.')
                ->disabled(fn (): bool => $this->unapprovedClientIds()->isEmpty())
                ->tooltip(fn (): ?string => $this->unapprovedClientIds()->isEmpty()
                    ? 'Every client has already approved or disputed their timesheets for this period.'
                    : null)
                ->action(function (): void {
                    $period = $this->currentPeriod();
                    $clientIds = $this->unapprovedClientIds();

                    foreach ($clientIds as $clientId) {
                        SendPayrollConfirmationEmail::dispatch(Client::findOrFail($clientId), $period['start']->toDateString());
                    }

                    Notification::make()
                        ->title($clientIds->count().' reminder email(s) queued')
                        ->success()
                        ->send();
                }),
        ]);
    }

    protected function periodCompany(): Company
    {
        return Auth::user()->company;
    }

    /**
     * Clients with at least one day this period that has never been sent a
     * confirmation at all — a brand new/late-added booking, or a day reset
     * back to unsent after a dispute gets resolved. This is deliberately
     * distinct from unapprovedClientIds(): a day that's already been sent
     * belongs to the reminder, not another initial confirmation.
     *
     * @return Collection<int, int>
     */
    private function unsentClientIds()
    {
        $period = $this->currentPeriod();

        return Booking::query()
            ->visibleToCurrentUser()
            ->excludingRequests()
            ->whereHas('dayPeriods', function ($query) use ($period): void {
                $query->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
                    ->whereNull('cancelled_at')
                    ->whereNull('payroll_confirmation_sent_at');
            })
            ->pluck('client_id')
            ->unique();
    }

    /**
     * Clients with at least one already-sent, still-unresolved day this
     * period — i.e. the client has neither approved nor disputed it. A day
     * that was never sent in the first place belongs to the Confirm/Resend
     * action instead, not a reminder.
     *
     * @return Collection<int, int>
     */
    private function unapprovedClientIds()
    {
        $period = $this->currentPeriod();

        return Booking::query()
            ->visibleToCurrentUser()
            ->excludingRequests()
            ->whereHas('dayPeriods', function ($query) use ($period): void {
                $query->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
                    ->whereNull('cancelled_at')
                    ->whereNotNull('payroll_confirmation_sent_at')
                    ->whereNull('approved_at')
                    ->whereNull('disputed_at');
            })
            ->pluck('client_id')
            ->unique();
    }

    private function hasAnyConfirmationBeenSent(): bool
    {
        $period = $this->currentPeriod();

        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->visibleToCurrentUser()->excludingRequests())
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->whereNull('cancelled_at')
            ->whereNotNull('payroll_confirmation_sent_at')
            ->exists();
    }
}
