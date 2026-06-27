<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\CandidateSkillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class CandidateSkill extends Model
{
    /** @use HasFactory<CandidateSkillFactory> */
    use BelongsToCompany;

    use HasFactory;

    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CandidateSkill::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CandidateSkill::class, 'parent_id')->withoutGlobalScopes();
    }

    public function candidates(): MorphToMany
    {
        $modelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if ($modelClass === null) {
            return $this->morphedByMany(EducationCandidate::class, 'candidate', 'candidate_skill_candidates')
                ->whereRaw('0 = 1');
        }

        return $this->morphedByMany($modelClass, 'candidate', 'candidate_skill_candidates');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
