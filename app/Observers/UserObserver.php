<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
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

    /**
     * The candidate record itself is never re-saved when their application
     * completes (see completeApplication()'s comment) — this User being
     * created/linked is the only signal that fires afterwards, so it's the
     * point both the status automations and the Action/Todo rules need to
     * be re-evaluated against the candidate.
     */
    private function checkAutomations(User $user): void
    {
        if (! $user->candidate_id || ! $user->candidate_type || ! class_exists($user->candidate_type)) {
            return;
        }

        if ($user->candidate) {
            CheckCandidateStatusAutomations::run($user->candidate);
            CheckActions::run($user->candidate);
        }
    }
}
