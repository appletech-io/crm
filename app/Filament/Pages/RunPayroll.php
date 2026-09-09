<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPayrollBookingsTable;
use App\Filament\Concerns\HasTimesheetPeriodNavigation;
use App\Filament\Support\ExportPayrollCsvAction;
use App\Filament\Support\ExportPayrollTimesheetsZipAction;
use App\Jobs\SendPayrollConfirmationEmail;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
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
            ExportPayrollTimesheetsZipAction::header(
                fn () => $this->dayPeriodsQuery()->get(),
                fn () => $this->currentPeriod(),
                fn () => $this->periodCompany(),
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
        ])
            ->recordActions([$this->approveDayAction()])
            ->toolbarActions([$this->approveSelectedDaysAction()])
            ->checkIfRecordIsSelectableUsing(fn (BookingDay $record): bool => $this->canApprove($record));
    }

    /**
     * Lets an admin sign a day off on the client's behalf — the client has
     * confirmed by phone or email, or simply isn't going to use the portal.
     * Only offered on this page: ViewPayroll is a read-only view of a
     * consultant's own bookings, and the client's own approval lives on
     * their portal's My Bookings page.
     */
    private function approveDayAction(): Action
    {
        return Action::make('approveDay')
            ->label('Approve')
            ->icon('heroicon-o-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve this day on the client\'s behalf?')
            ->modalDescription('Recorded as approved by you rather than by the client. Once every sent day on the booking is approved, the booking moves to Approved and its timesheet is submitted to your payroll provider.')
            ->visible(fn (BookingDay $record): bool => $this->canApprove($record))
            ->action(function (BookingDay $record): void {
                $this->approveDays(collect([$record]));

                Notification::make()
                    ->title('Day approved')
                    ->success()
                    ->send();
            });
    }

    private function approveSelectedDaysAction(): BulkAction
    {
        return BulkAction::make('approveSelectedDays')
            ->label('Approve selected days')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve the selected days on the client\'s behalf?')
            ->modalDescription('Recorded as approved by you rather than by the client. Any booking whose sent days are then all approved moves to Approved and has its timesheet submitted to your payroll provider.')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $approved = $this->approveDays($records);

                Notification::make()
                    ->title($approved.' day(s) approved')
                    ->success()
                    ->send();
            });
    }

    /**
     * A day can only be approved once its confirmation has actually gone
     * out: {@see Booking::refreshPayrollStatus()} derives the booking's
     * status purely from days that have been sent, so approving an unsent
     * day would set approved_at without ever progressing the booking or
     * reaching payroll — a silent dead end. The Confirm action above is
     * what sends those days in the first place.
     *
     * Already-approved days are excluded, but a disputed one is not: an
     * admin resolving a dispute in the client's favour is exactly what this
     * is for.
     */
    private function canApprove(BookingDay $day): bool
    {
        return $day->isPayrollConfirmationSent() && $day->payrollStatus()->isAwaitingApproval();
    }

    /**
     * Refreshes each affected booking once rather than once per day —
     * refreshPayrollStatus() reads the whole booking's days back from the
     * database, so per-day calls would repeat the same work and, on the
     * final one, re-evaluate a status that has already settled.
     *
     * @param  Collection<int, BookingDay>  $days
     * @return int the number of days actually approved
     */
    private function approveDays(Collection $days): int
    {
        $approvable = $days->filter(fn (BookingDay $day): bool => $this->canApprove($day));

        foreach ($approvable as $day) {
            $day->update([
                'approved_at' => now(),
                'approved_by_user_id' => Auth::id(),
                'disputed_at' => null,
                'dispute_reason' => null,
            ]);
        }

        $approvable->pluck('booking')->filter()->unique('id')
            ->each(fn (Booking $booking) => $booking->refreshPayrollStatus());

        return $approvable->count();
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
