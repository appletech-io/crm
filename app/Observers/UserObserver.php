<?php

namespace App\Observers;

use App\Actions\Candidates\CheckCandidateStatusAutomations;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        $this->checkAutomations($user);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('candidate_id')) {
            $this->checkAutomations($user);
        }
    }

    private function checkAutomations(User $user): void
    {
        if (! $user->candidate_id || ! $user->candidate_type || ! class_exists($user->candidate_type)) {
            return;
        }

        if ($user->candidate) {
            CheckCandidateStatusAutomations::run($user->candidate);
        }
    }
}
