<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Actions\Candidates\RecalculateCandidateRating;
use App\Models\Booking;

class BookingObserver
{
    public function saved(Booking $booking): void
    {
        CheckActions::run($booking);
        $this->recalculateCandidateRating($booking);
    }

    public function deleted(Booking $booking): void
    {
        CheckActions::run($booking);
        $this->recalculateCandidateRating($booking);
    }

    public function restored(Booking $booking): void
    {
        $this->recalculateCandidateRating($booking);
    }

    /**
     * Covers every path that can change a booking's candidate_rating (or
     * remove/bring back a rated booking) in one place, rather than special
     * casing RateBookings::rate() alone.
     */
    private function recalculateCandidateRating(Booking $booking): void
    {
        $candidate = $booking->candidate()->withTrashed()->first();

        if ($candidate) {
            RecalculateCandidateRating::run($candidate);
        }
    }
}
