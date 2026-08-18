<?php

namespace App\Models;

use Database\Factories\QualificationJobTitleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class QualificationJobTitle extends Pivot
{
    /** @use HasFactory<QualificationJobTitleFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'qualification_job_titles';

    protected $guarded = [];

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }
}
