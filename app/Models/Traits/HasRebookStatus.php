<?php

namespace App\Models\Traits;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Whether a candidate (Education or Healthcare) still needs a booking lined
 * up for a given week — drives the "Rebook" action on the candidates table
 * so a consultant can see who's coming up with nothing booked next week.
 *
 * Candidate-level, not client-level: a candidate already booked with one
 * client but not another still counts as booked, since the question this
 * answers is "does this candidate have any work that week", not "are they
 * working for this specific client".
 */
trait HasRebookStatus
{
    public function needsRebookForWeek(CarbonInterface $weekStart): bool
    {
        $start = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        return ! $this->bookings()
            ->excludingRequests()
            ->whereHas('dayPeriods', fn ($query) => $query
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->whereNull('cancelled_at'))
            ->exists();
    }
}
