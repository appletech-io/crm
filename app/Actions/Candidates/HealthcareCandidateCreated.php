<?php

namespace App\Actions\Candidates;

use App\Jobs\SendHealthcareApplicationEmail;
use App\Models\CandidateStatus;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class HealthcareCandidateCreated
{
    use AsAction;

    public function handle(HealthcareCandidate $candidate): void
    {
        /** @var HealthcareApplication $application */
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

        SendHealthcareApplicationEmail::dispatch($candidate, $application);
    }
}
