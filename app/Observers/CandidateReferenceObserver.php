<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Enums\ActivityType;
use App\Enums\ReferenceStatus;
use App\Models\CandidateReference;

class CandidateReferenceObserver
{
    public function saved(CandidateReference $reference): void
    {
        if ($reference->wasChanged('status') && $reference->status === ReferenceStatus::Submitted) {
            $this->logCompleted($reference);
        }

        CheckActions::run($reference);
    }

    private function logCompleted(CandidateReference $reference): void
    {
        $candidate = $reference->candidate;

        if (! $candidate) {
            return;
        }

        $refereeName = trim("{$reference->first_name} {$reference->last_name}") ?: 'The referee';

        $candidate->activities()->create([
            'user_id' => $candidate->consultant_id,
            'type' => ActivityType::Other->value,
            'note' => 'Reference completed',
            'body' => "{$refereeName} submitted their reference response.",
        ]);
    }
}
