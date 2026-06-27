<?php

namespace App\Actions\Candidates;

use App\Enums\ActivityType;
use App\Jobs\SendApplicationEmail;
use App\Models\CandidateStatus;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CandidateCreated
{
    use AsAction;

    public function handle(EducationCandidate $candidate): void
    {
        /** @var EducationApplication $application */
        $application = $candidate->application()->create([
            'email' => $candidate->email,
            'status' => 'pending',
            'token' => Str::uuid(),
            'expires_on' => now()->addDays(7)->toDateString(),
        ]);

        $onboarding = CandidateStatus::where('company_id', $candidate->company_id)
            ->where('industry_id', active_industry_id())
            ->where('name', 'Onboarding')
            ->first();

        if ($onboarding) {
            $candidate->statuses()->firstOrCreate(['candidate_status_id' => $onboarding->id]);
        }

        SendApplicationEmail::dispatch($candidate, $application);
    }
}
