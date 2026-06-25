<?php

namespace App\Actions\Candidates;

use App\Enums\ActivityType;
use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

class ChangeCandidateStatus
{
    use AsAction;

    public function handle(Model $candidate, CandidateStatusAutomation $automation): void
    {
        $fromStatus = CandidateStatus::find($automation->candidate_status_id);
        $toStatus = CandidateStatus::find($automation->to_candidate_status_id);

        $candidate->statuses()
            ->where('candidate_status_id', $automation->candidate_status_id)
            ->delete();

        $candidate->statuses()->firstOrCreate([
            'candidate_status_id' => $automation->to_candidate_status_id,
        ]);

        $candidate->activities()->create([
            'user_id' => null,
            'type' => ActivityType::StatusAutomation->value,
            'note' => "Status automatically changed from \"{$fromStatus?->name}\" to \"{$toStatus?->name}\"",
            'body' => json_encode([
                'from' => $fromStatus?->name,
                'to' => $toStatus?->name,
                'required_fields' => $automation->completed_fields,
                'snapshot' => $candidate->toArray(),
            ]),
            'contacted' => false,
        ]);
    }
}
