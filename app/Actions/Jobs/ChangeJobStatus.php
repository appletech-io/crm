<?php

namespace App\Actions\Jobs;

use App\Enums\ActivityType;
use App\Models\JobStatus;
use App\Models\JobStatusAutomation;
use App\Models\Vacancy;
use Lorisleiva\Actions\Concerns\AsAction;

class ChangeJobStatus
{
    use AsAction;

    public function handle(Vacancy $vacancy, JobStatusAutomation $automation): void
    {
        $fromStatus = JobStatus::find($automation->job_status_id);
        $toStatus = JobStatus::find($automation->to_job_status_id);

        $vacancy->update(['job_status_id' => $automation->to_job_status_id]);

        $vacancy->activities()->create([
            'user_id' => null,
            'type' => ActivityType::StatusAutomation->value,
            'note' => "Status automatically changed from \"{$fromStatus?->name}\" to \"{$toStatus?->name}\"",
            'body' => json_encode([
                'from' => $fromStatus?->name,
                'to' => $toStatus?->name,
                'conditions' => $automation->conditions,
                'snapshot' => $vacancy->toArray(),
            ]),
            'contacted' => false,
        ]);
    }
}
