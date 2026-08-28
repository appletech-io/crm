<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Actions\Candidates\CheckCandidateStatusAutomations;
use App\Jobs\SyncPayrollProviderRecord;
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
     * Only consultants are ever registered with the payroll provider — a
     * role assigned separately via syncRoles()/assignRole() (a pivot-table
     * write, not a save on this model) won't re-trigger this on its own, so
     * a brand new consultant's very first sync may wait until their next
     * unrelated save. The booking-approval sync remains as a safety net.
     */
    public function saved(User $user): void
    {
        if ($user->hasRole('consultant')) {
            SyncPayrollProviderRecord::dispatch($user);
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
