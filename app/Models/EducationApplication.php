<?php

namespace App\Models;

use Database\Factories\EducationApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationApplication extends Model
{
    /** @use HasFactory<EducationApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'education_candidate_id',
        'email',
        'email_verified',
        'status',
        'current_step',
        'token',
        'expires_on',
        'completed_at',
        'cv_temp_path',
        'cv_parsed_data',
        'terms_of_engagement_accepted_at',
        'terms_accepted_at',
        'declaration_accepted_at',
    ];

    protected $casts = [
        'expires_on' => 'date',
        'completed_at' => 'datetime',
        'email_verified' => 'boolean',
        'cv_parsed_data' => 'array',
        'current_step' => 'integer',
        'terms_of_engagement_accepted_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'declaration_accepted_at' => 'datetime',
    ];

    public function educationCandidate(): BelongsTo
    {
        return $this->belongsTo(EducationCandidate::class);
    }
}
