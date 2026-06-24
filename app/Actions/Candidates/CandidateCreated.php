<?php

namespace App\Actions\Candidates;

use App\Enums\ActivityType;
// use App\Jobs\SendApplicationEmail;
use App\Models\EducationCandidate;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CandidateCreated
{
    use AsAction;

    public function handle(EducationCandidate $candidate): void
    {
        // 1. Create the application record
        $application = $candidate->application()->create([
            'email' => $candidate->email,
            'status' => 'pending',
            'token' => Str::uuid(),
            'expires_on' => now()->addDays(7)->toDateString(),
        ]);

        // @TODO 2. Dispatch email job - when emails are worked on
        //SendApplicationEmail::dispatch($candidate, $application);

        // 3. Log activity
        $candidate->activities()->create([
            'user_id' => auth()->id(),
            'type' => ActivityType::Email->value,
            'note' => 'Application pack sent',
            'body' => "Application email sent to {$candidate->email} with application link.",
            'contacted' => true,
        ]);
    }
}
