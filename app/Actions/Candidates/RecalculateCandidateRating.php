<?php

namespace App\Actions\Candidates;

use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateCandidateRating
{
    use AsAction;

    /**
     * average_rating/ratings_count are stored on the candidate (rather than
     * computed live) so they can be filtered and sorted on directly — this
     * is what keeps them in sync, run from BookingObserver whenever a
     * booking is saved, deleted, or restored. updateQuietly() since this is
     * housekeeping on a computed field, not a change anyone needs notified
     * or automated on.
     */
    public function handle(Model $candidate): void
    {
        $stats = $candidate->bookings()
            ->whereNotNull('candidate_rating')
            ->selectRaw('avg(candidate_rating) as average_rating, count(*) as ratings_count')
            ->first();

        $candidate->updateQuietly([
            'average_rating' => $stats->ratings_count > 0 ? round((float) $stats->average_rating, 2) : null,
            'ratings_count' => $stats->ratings_count,
        ]);
    }
}
