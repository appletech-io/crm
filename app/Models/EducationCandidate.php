<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\EducationCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationCandidate extends Model
{
    /** @use HasFactory<EducationCandidateFactory> */
    use BelongsToCompany;

    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date',
        'availability' => 'array',
        'key_stages' => 'array',
        'education_and_qualification' => 'string',
        'employment_history' => 'string',
    ];

    public function application(): HasOne
    {
        return $this->hasOne(EducationApplication::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class);
    }
}
