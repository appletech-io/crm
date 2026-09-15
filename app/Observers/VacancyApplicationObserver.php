<?php

namespace App\Observers;

use App\Models\JobStatus;
use App\Models\Vacancy;
use App\Models\VacancyApplication;

class VacancyApplicationObserver
{
    /**
     * New applications enter the candidate pipeline (the Applicants board on
     * the vacancy's edit page) at its first stage by default. Resolved
     * without the JobStatus company global scope: this fires from the public,
     * unauthenticated apply form as well as from admin-side actions, so it
     * can't rely on auth()->user()->company_id the way that scope does.
     */
    public function creating(VacancyApplication $application): void
    {
        if ($application->job_status_id) {
            return;
        }

        $vacancy = Vacancy::withoutGlobalScope('company')->find($application->vacancy_id);

        if (! $vacancy) {
            return;
        }

        $application->job_status_id = JobStatus::withoutGlobalScope('company')
            ->where('company_id', $vacancy->company_id)
            ->where('industry_id', $vacancy->industry_id)
            ->ordered()
            ->value('id');
    }
}
