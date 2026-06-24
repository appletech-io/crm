<?php

namespace App\Observers;

use App\Jobs\GeocodeEducationCandidate;
use App\Models\EducationCandidate;

class EducationCandidateObserver
{
    public function saved(EducationCandidate $candidate): void
    {
        if ($candidate->wasChanged('postcode') || ($candidate->wasRecentlyCreated && filled($candidate->postcode))) {
            GeocodeEducationCandidate::dispatch($candidate);
        }
    }
}
