<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CandidateCandidateStatus extends Model
{
    protected $table = 'candidate_candidate_status';

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CandidateStatus::class, 'candidate_status_id');
    }
}
