<?php

namespace App\Actions\Candidates;

use App\Jobs\SendApplicationEmail;
use App\Models\CandidateStatus;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class HealthcareCandidateCreated
{
    use AsAction;

    public function handle(HealthcareCandidate $candidate, bool $sync = false): void
    {
        /** @var HealthcareApplication $application */
        $application = $candidate->application()->create([
            'email' => $candidate->email,
            'status' => 'pending',
            'token' => Str::uuid(),
            'expires_on' => now()->addWeeks(2)->toDateString(),
        ]);

        $onboarding = CandidateStatus::where('company_id', $candidate->company_id)
            ->where('industry_id', active_industry_id())
            ->where('name', 'Onboarding')
            ->first();

        if ($onboarding) {
            $candidate->statuses()->firstOrCreate(['candidate_status_id' => $onboarding->id]);
        }

        if ($sync) {
            SendApplicationEmail::dispatchSync($candidate, $application, auth()->id());
        } else {
            SendApplicationEmail::dispatch($candidate, $application, auth()->id());
        }
    }
}
