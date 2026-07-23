<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Models\HealthcareCandidate;

class HealthcareCandidateObserver
{
    public function saved(HealthcareCandidate $candidate): void
    {
        CheckActions::run($candidate);
    }
}
