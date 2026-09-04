<?php

namespace App\Models;

use App\Enums\ReferenceStatus;
use App\Enums\ReferenceType;
use App\Models\Traits\HasFieldSuggestions;
use App\Services\References\ReferenceFormSchema;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CandidateReference extends Model
{
    use HasFieldSuggestions;

    protected $guarded = [];

    protected $casts = [
        'type' => ReferenceType::class,
        'status' => ReferenceStatus::class,
        'worked_from' => 'date',
        'worked_to' => 'date',
        'last_contacted' => 'date',
        'consent_to_contact' => 'boolean',
        'contact_now' => 'boolean',
        'expires_on' => 'date',
        'answers' => 'array',
        'schema' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }

    public function referenceForm(): BelongsTo
    {
        return $this->belongsTo(ReferenceForm::class);
    }

    /**
     * Whether this reference collects a free-text statement instead of a
     * referee filling in questions (the "Gap / Statement" concept) — reads
     * from the linked dynamic form when one is set, falling back to the
     * legacy enum case for references created before dynamic forms existed.
     */
    public function isStatementOnly(): bool
    {
        return $this->referenceForm?->is_statement_only
            ?? $this->type === ReferenceType::GapStatement;
    }

    /**
     * Whether the referee should be asked to confirm their own position and
     * organisation on the public form — false only for a Character-style
     * reference. Reads from the dynamic form's own column when set, else
     * falls back to the legacy hardcoded rule.
     */
    public function needsPositionAndOrganisation(): bool
    {
        if ($this->referenceForm) {
            return $this->referenceForm->needs_position_and_organisation;
        }

        return $this->type ? ReferenceFormSchema::needsPositionAndOrganisation($this->type) : true;
    }

    public function displayLabel(): string
    {
        return $this->referenceForm?->name ?? $this->type?->label() ?? 'Reference';
    }

    /**
     * A reference carries no company_id of its own — it's inferred from the
     * candidate it belongs to, the same way Booking/Vacancy infer theirs.
     * Queried directly rather than through the `candidate` relation so this
     * never leaves a stale cached relation on the reference instance — this
     * accessor runs unconditionally on every save (CheckActions reads it
     * up front), so caching here would poison any later read of ->candidate
     * on the same instance if the candidate changed in the meantime.
     */
    protected function companyId(): Attribute
    {
        return Attribute::make(
            get: function (): ?int {
                if (! $this->candidate_type || ! $this->candidate_id) {
                    return null;
                }

                return $this->candidate_type::query()->whereKey($this->candidate_id)->value('company_id');
            },
        );
    }
}
