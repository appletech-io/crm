<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Jobs\GeocodeHealthcareCandidate;
use App\Models\HealthcareCandidate;

class HealthcareCandidateObserver
{
    public function saved(HealthcareCandidate $candidate): void
    {
        if ($candidate->wasChanged('postcode') || ($candidate->wasRecentlyCreated && filled($candidate->postcode))) {
            GeocodeHealthcareCandidate::dispatch($candidate);
        }

        CheckActions::run($candidate);
    }

    public function deleted(HealthcareCandidate $candidate): void
    {
        CheckActions::run($candidate);
    }
}
