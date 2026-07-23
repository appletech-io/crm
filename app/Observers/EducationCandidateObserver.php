<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Actions\Candidates\CheckCandidateStatusAutomations;
use App\Jobs\GeocodeEducationCandidate;
use App\Models\EducationCandidate;

class EducationCandidateObserver
{
    public function saved(EducationCandidate $candidate): void
    {
        if ($candidate->wasChanged('postcode') || ($candidate->wasRecentlyCreated && filled($candidate->postcode))) {
            GeocodeEducationCandidate::dispatch($candidate);
        }

        CheckActions::run($candidate);
    }

    public function updated(EducationCandidate $candidate): void
    {
        CheckCandidateStatusAutomations::run($candidate);
    }
}
