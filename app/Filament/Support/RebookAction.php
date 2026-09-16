<?php

namespace App\Filament\Support;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Surfaces, per booking row, whether that booking's candidate is working
 * this week but still needs a booking lined up for next week — visible only
 * when the row has a booked day this week (so it doesn't light up on old,
 * unrelated history) and the candidate has nothing booked at all next week
 * (see HasRebookStatus::needsRebookForWeek()), which is candidate-level
 * rather than tied to this specific booking's client. Clicking opens the
 * booking-creation form pre-filled with the same candidate, client, and job
 * title as this row, for next week's Monday.
 */
class RebookAction
{
    public static function make(): Action
    {
        return Action::make('rebook')
            ->label('Rebook')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('warning')
            ->visible(fn (Booking $record): bool => static::isBookedThisWeek($record)
                && (bool) $record->candidate?->needsRebookForWeek(static::nextWeekStart()))
            ->url(fn (Booking $record): string => BookingResource::getUrl('create', [
                'candidate_id' => $record->candidate_id,
                'client_id' => $record->client_id,
                'job_title_id' => $record->job_title_id,
                'start_date' => static::nextWeekStart()->toDateString(),
            ]));
    }

    private static function isBookedThisWeek(Booking $record): bool
    {
        $start = now()->startOfWeek(CarbonInterface::MONDAY);
        $end = $start->copy()->endOfWeek(CarbonInterface::SUNDAY);

        return $record->dayPeriods()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNull('cancelled_at')
            ->exists();
    }

    public static function nextWeekStart(): CarbonInterface
    {
        return now()->addWeek()->startOfWeek(CarbonInterface::MONDAY);
    }
}
