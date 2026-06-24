<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Attribute;
use Database\Factories\EducationCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    protected $appends = ['display_name'];

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->first_name
                ? trim("{$this->first_name} {$this->last_name}")
                : $this->email,
        );
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->first_name
            ? trim("{$this->first_name} {$this->last_name}")
            : $this->email;
    }

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

    public function activities(): MorphMany
    {
        return $this->morphMany(CandidateActivity::class, 'model')->latest();
    }
}
