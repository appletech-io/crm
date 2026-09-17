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

    public function saved(VacancyApplication $application): void
    {
        $this->advanceVacancyIfFurtherAlong($application);
    }

    /**
     * If this candidate now sits at a later pipeline stage than the
     * vacancy's own current status, the vacancy moves up to match — never
     * backward, so an earlier-stage candidate (new, or moved back) never
     * regresses a job that's already moved on. Resolved without the
     * company global scopes for the same reason as creating() above.
     */
    private function advanceVacancyIfFurtherAlong(VacancyApplication $application): void
    {
        if (! $application->job_status_id) {
            return;
        }

        $vacancy = Vacancy::withoutGlobalScope('company')->find($application->vacancy_id);

        if (! $vacancy) {
            return;
        }

        $sortOrders = JobStatus::withoutGlobalScope('company')
            ->whereIn('id', array_filter([$application->job_status_id, $vacancy->job_status_id]))
            ->pluck('sort_order', 'id');

        $candidateSortOrder = $sortOrders[$application->job_status_id] ?? null;
        $vacancySortOrder = $sortOrders[$vacancy->job_status_id] ?? null;

        if ($candidateSortOrder === null) {
            return;
        }

        if ($vacancySortOrder !== null && $candidateSortOrder <= $vacancySortOrder) {
            return;
        }

        $vacancy->update(['job_status_id' => $application->job_status_id]);
    }
}
