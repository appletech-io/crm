<?php

namespace App\Actions\Applications;

use App\Enums\ActivityType;
use App\Models\EducationApplication;
use Lorisleiva\Actions\Concerns\AsAction;

class ApplicationCompleted
{
    use AsAction;

    public function handle(EducationApplication $application): void
    {
        $candidate = $application->educationCandidate;

        $candidate->activities()->create([
            'user_id' => $candidate->consultant_id ?? auth()->id(),
            'type' => ActivityType::Note,
            'note' => 'Application pack completed',
            'body' => 'Candidate has completed the Application pack.',
            'contacted' => true,
        ]);
    }
}
