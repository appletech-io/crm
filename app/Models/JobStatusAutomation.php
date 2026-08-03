<?php

namespace App\Models;

use App\Models\Traits\EvaluatesConditions;
use Database\Factories\JobStatusAutomationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobStatusAutomation extends Model
{
    /** @use HasFactory<JobStatusAutomationFactory> */
    use EvaluatesConditions;

    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'conditions' => 'array',
    ];

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(JobStatus::class, 'job_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(JobStatus::class, 'to_job_status_id');
    }
}
