<?php

namespace App\Observers;

use App\Actions\Jobs\CheckJobStatusAutomations;
use App\Models\VacancyPlacement;

class VacancyPlacementObserver
{
    /**
     * Adding or removing a placement can flip Vacancy::placements_filled
     * (it depends on placement count as well as candidate status), so
     * re-run the vacancy's Job Status Automations here too — not just from
     * CandidateCandidateStatusObserver — to cover a candidate whose status
     * was already filled before being placed.
     */
    public function created(VacancyPlacement $placement): void
    {
        $this->checkAutomations($placement);
    }

    public function deleted(VacancyPlacement $placement): void
    {
        $this->checkAutomations($placement);
    }

    private function checkAutomations(VacancyPlacement $placement): void
    {
        if ($placement->vacancy) {
            CheckJobStatusAutomations::run($placement->vacancy);
        }
    }
}
