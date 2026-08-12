<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Actions\Jobs\CheckJobStatusAutomations;
use App\Models\JobStatus;
use App\Models\Vacancy;

class VacancyObserver
{
    public function saved(Vacancy $vacancy): void
    {
        CheckActions::run($vacancy);
    }

    public function updated(Vacancy $vacancy): void
    {
        $this->syncFilledAt($vacancy);

        CheckJobStatusAutomations::run($vacancy);
    }

    /**
     * filled_at only has one job: letting reporting know when a placement
     * actually happened. It tracks the *current* filled-type status, not the
     * first time one was ever reached — moving back out of a filled status
     * clears it so a reopened vacancy doesn't keep a stale placement date.
     */
    private function syncFilledAt(Vacancy $vacancy): void
    {
        if (! $vacancy->wasChanged('job_status_id')) {
            return;
        }

        $isFilled = JobStatus::withoutGlobalScope('company')
            ->where('id', $vacancy->job_status_id)
            ->value('is_filled_status');

        if ($isFilled && ! $vacancy->filled_at) {
            $vacancy->updateQuietly(['filled_at' => now()]);
        } elseif (! $isFilled && $vacancy->filled_at) {
            $vacancy->updateQuietly(['filled_at' => null]);
        }
    }

    public function deleted(Vacancy $vacancy): void
    {
        CheckActions::run($vacancy);
        CheckJobStatusAutomations::run($vacancy);
    }
}
