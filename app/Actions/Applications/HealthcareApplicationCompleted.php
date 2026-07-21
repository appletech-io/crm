<?php

namespace App\Actions\Applications;

use App\Enums\ActivityType;
use App\Models\HealthcareApplication;
use Lorisleiva\Actions\Concerns\AsAction;

class HealthcareApplicationCompleted
{
    use AsAction;

    public function handle(HealthcareApplication $application): void
    {
        $candidate = $application->candidate;

        $candidate->activities()->create([
            'user_id' => $candidate->consultant_id ?? auth()->id(),
            'type' => ActivityType::Note,
            'note' => 'Application pack completed',
            'body' => 'Candidate has completed the Application pack.',
            'contacted' => true,
        ]);
    }
}
