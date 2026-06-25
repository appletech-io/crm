<?php

namespace App\Models;

use Database\Factories\CandidateStatusAutomationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateStatusAutomation extends Model
{
    /** @use HasFactory<CandidateStatusAutomationFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'completed_fields' => 'array',
    ];

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(CandidateStatus::class, 'candidate_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(CandidateStatus::class, 'to_candidate_status_id');
    }

    /**
     * Check whether the given candidate satisfies all required fields.
     */
    public function isSatisfiedBy(Model $candidate): bool
    {
        foreach ($this->completed_fields as $path) {
            if (! $this->evaluatePath($candidate, $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a dot-notation path against the candidate model.
     *
     * Plain attribute:   "first_name"
     * Wildcard relation: "skills.*"  → relation must have at least one record
     */
    private function evaluatePath(Model $candidate, string $path): bool
    {
        if (str_ends_with($path, '.*')) {
            $relation = rtrim($path, '.*');

            return $candidate->{$relation}()->exists();
        }

        return filled(data_get($candidate, $path));
    }
}
