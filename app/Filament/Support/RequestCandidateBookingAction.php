<?php

namespace App\Filament\Support;

use App\Enums\BookingStatus;
use App\Filament\Forms\Components\DayScheduleCalendar;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The "request a new booking for this candidate" modal on the client
 * portal — shared between MyCandidates ("Book", for a pooled candidate) and
 * MyBookings ("Rebook", for a candidate the client already has a booking
 * with), so both surfaces build the exact same request and land in the
 * same Requested-status booking for the consultant to confirm.
 */
class RequestCandidateBookingAction
{
    /**
     * @param  callable(mixed $record): Model  $candidateResolver  Resolves the actual candidate model from whatever record the host table's row represents.
     */
    public static function make(string $name, string $label, callable $candidateResolver): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedCalendarDays)
            ->modalHeading(fn (mixed $record): string => 'Request a booking for '.static::candidateName($candidateResolver($record)))
            ->modalSubmitActionLabel('Request Booking')
            ->schema([
                DatePicker::make('start_date')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get) => static::regenerateDayPeriods($set, $get)),
                DatePicker::make('end_date')
                    ->live()
                    ->minDate(fn (Get $get): mixed => $get('start_date'))
                    ->afterStateUpdated(fn (Set $set, Get $get) => static::regenerateDayPeriods($set, $get)),
                DayScheduleCalendar::make('day_periods')
                    ->hiddenLabel()
                    ->live()
                    ->hoursEnabled(false)
                    ->visible(fn (Get $get): bool => filled($get('start_date')))
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Notes for your consultant')
                    ->rows(3),
            ])
            ->action(function (mixed $record, array $data) use ($candidateResolver): void {
                $candidate = $candidateResolver($record);
                $client = Auth::user()->client();

                $booking = Booking::create([
                    'client_id' => $client->id,
                    'consultant_id' => $client->consultant_id,
                    'candidate_type' => $candidate::class,
                    'candidate_id' => $candidate->id,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'status' => BookingStatus::Requested,
                ]);

                BookingForm::syncDayPeriods($booking, $data['day_periods'] ?? []);

                Notification::make()
                    ->success()
                    ->title('Booking requested — your consultant will confirm the details shortly.')
                    ->send();
            });
    }

    public static function regenerateDayPeriods(Set $set, Get $get): void
    {
        // active_industry() (BookingForm::dayPeriodsForRange()'s own default)
        // only reflects a logged-in staff member's switchable sector — a
        // client portal login has no such toggle, so the weekend default has
        // to come from the client's own fixed industry instead.
        $weekendsDefaultToNA = Auth::user()->client()->industry?->slug === 'education';

        $set('day_periods', BookingForm::dayPeriodsForRange(
            $get('start_date'),
            $get('end_date'),
            $get('day_periods') ?? [],
            weekendsDefaultToNA: $weekendsDefaultToNA,
        ));
    }

    private static function candidateName(Model $candidate): string
    {
        return trim("{$candidate->first_name} {$candidate->last_name}");
    }
}
