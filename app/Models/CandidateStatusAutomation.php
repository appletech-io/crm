<?php

namespace App\Models;

use App\Models\Traits\EvaluatesConditions;
use Database\Factories\CandidateStatusAutomationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateStatusAutomation extends Model
{
    /** @use HasFactory<CandidateStatusAutomationFactory> */
    use EvaluatesConditions;

    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'conditions' => 'array',
    ];

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(CandidateStatus::class, 'candidate_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(CandidateStatus::class, 'to_candidate_status_id');
    }
}
