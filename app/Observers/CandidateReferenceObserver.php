<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Models\CandidateReference;

class CandidateReferenceObserver
{
    public function saved(CandidateReference $reference): void
    {
        CheckActions::run($reference);
    }
}
