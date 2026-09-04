<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Enums\ActivityType;
use App\Enums\ReferenceStatus;
use App\Models\CandidateReference;
use App\Models\ReferenceForm;
use App\Services\References\ReferenceFormRenderer;

class CandidateReferenceObserver
{
    /**
     * Freeze the form's current questions onto this reference, once, at
     * creation — see ReferenceFormRenderer's class docblock for why this
     * never happens again on update (a resend must not re-snapshot).
     */
    public function creating(CandidateReference $reference): void
    {
        if (! $reference->reference_form_id || $reference->schema !== null) {
            return;
        }

        $form = ReferenceForm::with('fields')->find($reference->reference_form_id);

        if (! $form) {
            return;
        }

        $reference->schema = ReferenceFormRenderer::snapshotFor($form, $this->companyNameFor($reference));
    }

    /**
     * A reference that has already been sent (or resolved) must never have
     * its type changed — the referee may be mid-way through the exact
     * questions it was issued with. The Filament repeaters already disable
     * this field once a reference leaves Pending; this is the backend
     * enforcement of that same rule against a bypassed frontend or a direct
     * model update.
     *
     * For a still-Pending reference, changing its form is legitimate — no
     * referee has seen any questions yet — so its schema snapshot is
     * refreshed to match the newly selected form.
     */
    public function updating(CandidateReference $reference): void
    {
        if (! $reference->isDirty('reference_form_id')) {
            return;
        }

        if ($reference->getOriginal('status') !== ReferenceStatus::Pending) {
            $reference->reference_form_id = $reference->getOriginal('reference_form_id');

            return;
        }

        if (! $reference->reference_form_id) {
            $reference->schema = null;

            return;
        }

        $form = ReferenceForm::with('fields')->find($reference->reference_form_id);

        $reference->schema = $form ? ReferenceFormRenderer::snapshotFor($form, $this->companyNameFor($reference)) : null;
    }

    public function saved(CandidateReference $reference): void
    {
        if ($reference->wasChanged('status') && $reference->status === ReferenceStatus::Submitted) {
            $this->logCompleted($reference);
        }

        CheckActions::run($reference);
    }

    private function companyNameFor(CandidateReference $reference): string
    {
        $company = $reference->candidate?->company;

        return $company?->trading_name ?: config('app.name');
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
