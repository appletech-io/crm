<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Jobs\GeocodeCandidate;
use App\Jobs\SyncPayrollProviderRecord;
use App\Models\Candidate;

class CandidateObserver
{
    public function saved(Candidate $candidate): void
    {
        if ($candidate->wasChanged('postcode') || ($candidate->wasRecentlyCreated && filled($candidate->postcode))) {
            GeocodeCandidate::dispatch($candidate);
        }

        CheckActions::run($candidate);

        SyncPayrollProviderRecord::dispatch($candidate);
    }

    public function deleted(Candidate $candidate): void
    {
        CheckActions::run($candidate);
    }
}
