<?php

namespace App\Filament\Support;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Surfaces, per candidate, whether a consultant still needs to line up a
 * booking for next week — visible only when the candidate has nothing
 * booked at all next week (see HasRebookStatus::needsRebookForWeek()).
 * Clicking opens the booking-creation form pre-filled with the candidate
 * and next week's Monday, ready for the consultant to pick a client.
 */
class RebookAction
{
    public static function make(): Action
    {
        return Action::make('rebook')
            ->label('Rebook')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('warning')
            ->visible(fn (EducationCandidate|HealthcareCandidate $record): bool => $record->needsRebookForWeek(static::nextWeekStart()))
            ->url(fn (EducationCandidate|HealthcareCandidate $record): string => BookingResource::getUrl('create', [
                'candidate_id' => $record->getKey(),
                'start_date' => static::nextWeekStart()->toDateString(),
            ]));
    }

    public static function nextWeekStart(): CarbonInterface
    {
        return now()->addWeek()->startOfWeek(CarbonInterface::MONDAY);
    }
}
