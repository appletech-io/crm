<?php

namespace App\Actions\Jobs;

use App\Models\JobStatusAutomation;
use App\Models\Vacancy;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckJobStatusAutomations
{
    use AsAction;

    public function handle(Vacancy $vacancy): void
    {
        if (! $vacancy->job_status_id) {
            return;
        }

        JobStatusAutomation::query()
            ->where('job_status_id', $vacancy->job_status_id)
            ->get()
            ->each(function (JobStatusAutomation $automation) use ($vacancy): bool {
                if (! $automation->isSatisfiedBy($vacancy)) {
                    return true;
                }

                ChangeJobStatus::run($vacancy, $automation);

                return false;
            });
    }
}
