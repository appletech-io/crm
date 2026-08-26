<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Actions\Jobs\CheckJobStatusAutomations;
use App\Models\CandidateCandidateStatus;
use App\Models\VacancyPlacement;

class CandidateCandidateStatusObserver
{
    /**
     * A candidate's status change can flip Vacancy::placements_filled for
     * every vacancy they're recorded as placed against — re-run each of
     * those vacancies' Job Status Automations so a rule built on that
     * condition can fire. Which status (if any) the vacancy actually moves
     * to is entirely up to whatever automation the consultant has
     * configured; this only triggers the check.
     */
    public function created(CandidateCandidateStatus $status): void
    {
        // The candidate's own Actions (e.g. "Welcome email - Live
        // candidates") key off current_status, which only changes here —
        // not via a save() on the candidate itself. Without this, they'd
        // only ever fire by accident, whenever the candidate's record next
        // happens to be saved for an unrelated reason.
        if ($status->model) {
            CheckActions::run($status->model);
        }

        VacancyPlacement::query()
            ->where('candidate_type', $status->model_type)
            ->where('candidate_id', $status->model_id)
            ->with('vacancy')
            ->get()
            ->each(function (VacancyPlacement $placement): void {
                if ($placement->vacancy) {
                    CheckJobStatusAutomations::run($placement->vacancy);
                }
            });
    }
}
