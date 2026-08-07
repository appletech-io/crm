<?php

namespace App\Console\Commands;

use App\Actions\Jobs\CheckJobStatusAutomations;
use App\Models\JobStatusAutomation;
use App\Models\Vacancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automations:check-time-based-job-statuses')]
#[Description('Re-evaluate job status automations whose conditions depend on elapsed time, since nothing else triggers a re-check for them')]
class CheckTimeBasedJobStatusAutomations extends Command
{
    public function handle(): int
    {
        $statusIds = JobStatusAutomation::query()
            ->get()
            ->filter(fn (JobStatusAutomation $automation): bool => $automation->hasTimeBasedCondition())
            ->pluck('job_status_id')
            ->unique();

        if ($statusIds->isEmpty()) {
            return self::SUCCESS;
        }

        Vacancy::query()
            ->whereIn('job_status_id', $statusIds)
            ->chunkById(100, function ($vacancies): void {
                $vacancies->each(fn (Vacancy $vacancy) => CheckJobStatusAutomations::run($vacancy));
            });

        return self::SUCCESS;
    }
}
