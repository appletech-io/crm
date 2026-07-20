<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasTimesheetPeriodNavigation;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Company;
use App\Services\Booking\TimesheetPeriod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class ClientTimesheetOverview extends BaseWidget
{
    use HasTimesheetPeriodNavigation;

    protected int|string|array $columnSpan = 'full';

    public ?Client $record = null;

    public function mount(?Client $record = null): void
    {
        $this->record = $record;
        $this->periodStart = $record
            ? TimesheetPeriod::current($record->company)['start']->toDateString()
            : Carbon::now()->toDateString();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Timesheets')
            ->description(fn (): string => $this->periodLabel())
            ->query(fn () => $this->dayPeriodsQuery())
            ->recordUrl(fn (BookingDay $record): ?string => $record->booking
                ? BookingResource::getUrl('edit', ['record' => $record->booking])
                : null)
            ->columns([
                TextColumn::make('candidateName')
                    ->label('Candidate')
                    ->getStateUsing(fn (BookingDay $record): string => $this->candidateLabel($record)),
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
            ->headerActions([
                ...$this->periodNavigationActions(),
            ])
            ->defaultSort('date')
            ->paginated(false)
            ->emptyStateHeading('No bookings for this period.');
    }

    protected function periodCompany(): Company
    {
        return $this->record->company;
    }

    private function periodLabel(): string
    {
        $period = $this->currentPeriod();

        return $period['start']->format('jS M Y').' - '.$period['end']->format('jS M Y');
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

    private function dayPeriodsQuery()
    {
        $period = $this->currentPeriod();

        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->where('client_id', $this->record->id))
            ->where('company_id', $this->record->company_id)
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->with(['booking.client' => fn ($query) => $query->withTrashed(), 'booking.candidate' => fn ($query) => $query->withTrashed(), 'booking.jobTitle']);
    }
}
