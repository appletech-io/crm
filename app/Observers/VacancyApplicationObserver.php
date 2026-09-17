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
        $this->syncVacancyToFurthestCandidate($application->vacancy_id);
    }

    public function deleted(VacancyApplication $application): void
    {
        $this->syncVacancyToFurthestCandidate($application->vacancy_id);
    }

    /**
     * A vacancy's own status isn't tracked separately from where its
     * candidates actually are — it always matches whichever candidate has
     * progressed furthest through the pipeline (by JobStatus::sort_order),
     * so "Interview Stage 2" on the Job Pipeline reflects a real job the
     * moment any candidate reaches it, rather than needing someone to also
     * update the vacancy's own status by hand. Resolved without the company
     * global scopes for the same reason as creating() above.
     */
    private function syncVacancyToFurthestCandidate(int $vacancyId): void
    {
        $vacancy = Vacancy::withoutGlobalScope('company')->find($vacancyId);

        if (! $vacancy) {
            return;
        }

        $applicationStatusIds = VacancyApplication::query()
            ->where('vacancy_id', $vacancyId)
            ->whereNotNull('job_status_id')
            ->pluck('job_status_id');

        if ($applicationStatusIds->isEmpty()) {
            return;
        }

        $furthestStatusId = JobStatus::withoutGlobalScope('company')
            ->whereIn('id', $applicationStatusIds)
            ->orderByDesc('sort_order')
            ->value('id');

        if ($furthestStatusId && $furthestStatusId !== $vacancy->job_status_id) {
            $vacancy->update(['job_status_id' => $furthestStatusId]);
        }
    }
}
