<?php

namespace App\Filament\Support;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Surfaces, per booking row, whether that booking's candidate still needs a
 * booking lined up for next week — visible only when they have nothing
 * booked at all next week (see HasRebookStatus::needsRebookForWeek()), which
 * is candidate-level rather than tied to this specific booking's client.
 * Clicking opens the booking-creation form pre-filled with the same
 * candidate, client, and job title as this row, for next week's Monday.
 */
class RebookAction
{
    public static function make(): Action
    {
        return Action::make('rebook')
            ->label('Rebook')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('warning')
            ->visible(fn (Booking $record): bool => $record->candidate?->needsRebookForWeek(static::nextWeekStart()) ?? false)
            ->url(fn (Booking $record): string => BookingResource::getUrl('create', [
                'candidate_id' => $record->candidate_id,
                'client_id' => $record->client_id,
                'job_title_id' => $record->job_title_id,
                'start_date' => static::nextWeekStart()->toDateString(),
            ]));
    }

    public static function nextWeekStart(): CarbonInterface
    {
        return now()->addWeek()->startOfWeek(CarbonInterface::MONDAY);
    }
}
