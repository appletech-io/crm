<?php

namespace App\Filament\Client\Pages;

use App\Filament\Concerns\HasTimesheetPeriodNavigation;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Company;
use App\Services\Booking\TimesheetPeriod;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MyBookings extends Page implements HasTable
{
    use HasTimesheetPeriodNavigation;
    use InteractsWithTable;

    protected string $view = 'filament.client.pages.my-bookings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'My Bookings';

    protected static ?string $title = 'My Bookings';

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
            ->groups([
                Group::make('booking.candidate_id')
                    ->getTitleFromRecordUsing(fn (BookingDay $record): string => $this->candidateLabel($record))
                    ->collapsible(),
            ])
            ->defaultGroup('booking.candidate_id')
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('booking.jobTitle.name')
                    ->label('Job Title')
                    ->placeholder('—'),
                TextColumn::make('date')
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
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('approveDay')
                        ->label('Approve this day')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn (BookingDay $record) => $this->approveDay($record)),
                    Action::make('disputeDay')
                        ->label('Dispute this day')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->schema([
                            Textarea::make('reason')
                                ->label('Reason for dispute')
                                ->required(),
                        ])
                        ->action(fn (BookingDay $record, array $data) => $this->disputeDay($record, $data['reason'])),
                    Action::make('approveBooking')
                        ->label('Approve all days for this booking')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (BookingDay $record) => $this->approveBookingDays($record->booking_id)),
                    Action::make('disputeBooking')
                        ->label('Dispute all days for this booking')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->schema([
                            Textarea::make('reason')
                                ->label('Reason for dispute')
                                ->required(),
                        ])
                        ->action(fn (BookingDay $record, array $data) => $this->disputeBookingDays($record->booking_id, $data['reason'])),
                ]),
            ])
            ->headerActions([
                ...$this->periodNavigationActions(),
            ])
            ->defaultSort('date')
            ->paginated(false)
            ->emptyStateHeading('You have no bookings to review for this period.');
    }

    protected function periodCompany(): Company
    {
        return $this->client()->company;
    }

    private function approveDay(BookingDay $day): void
    {
        $day->update(['approved_at' => now(), 'disputed_at' => null, 'dispute_reason' => null]);
        $day->booking->refreshPayrollStatus();

        Notification::make()
            ->success()
            ->title('Day approved')
            ->send();
    }

    private function disputeDay(BookingDay $day, string $reason): void
    {
        $day->update(['disputed_at' => now(), 'dispute_reason' => $reason, 'approved_at' => null]);
        $day->booking->refreshPayrollStatus();

        Notification::make()
            ->success()
            ->title('Day disputed')
            ->send();
    }

    private function approveBookingDays(int $bookingId): void
    {
        $booking = $this->findBooking($bookingId);

        $this->bookingDaysQuery($bookingId)->update(['approved_at' => now(), 'disputed_at' => null, 'dispute_reason' => null]);
        $booking->refreshPayrollStatus();

        Notification::make()
            ->success()
            ->title('Booking approved')
            ->send();
    }

    private function disputeBookingDays(int $bookingId, string $reason): void
    {
        $booking = $this->findBooking($bookingId);

        $this->bookingDaysQuery($bookingId)->update(['disputed_at' => now(), 'dispute_reason' => $reason, 'approved_at' => null]);
        $booking->refreshPayrollStatus();

        Notification::make()
            ->success()
            ->title('Booking disputed')
            ->send();
    }

    private function candidateLabel(BookingDay $record): string
    {
        $candidate = $record->booking?->candidate;

        if (! $candidate) {
            return 'Unknown candidate';
        }

        return trim("{$candidate->first_name} {$candidate->last_name}");
    }

    private function client(): Client
    {
        /** @var Client $client */
        $client = Auth::user()->client();

        return $client;
    }

    private function dayPeriodsQuery()
    {
        $period = $this->currentPeriod();

        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->where('client_id', $this->client()->id))
            ->where('company_id', $this->client()->company_id)
            ->whereNotNull('payroll_confirmation_sent_at')
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->with(['booking.candidate', 'booking.jobTitle']);
    }

    private function findBooking(int $bookingId): Booking
    {
        return Booking::query()
            ->where('id', $bookingId)
            ->where('client_id', $this->client()->id)
            ->where('company_id', $this->client()->company_id)
            ->firstOrFail();
    }

    private function bookingDaysQuery(int $bookingId)
    {
        return $this->dayPeriodsQuery()->where('booking_id', $bookingId);
    }
}
