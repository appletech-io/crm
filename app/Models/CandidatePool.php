<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class CandidatePool extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function candidates(): MorphToMany
    {
        $modelClass = Industry::candidateModelForSlug(active_industry() ?? '');

        if ($modelClass === null) {
            return $this->morphedByMany(EducationCandidate::class, 'candidate', 'candidate_pool_candidates')
                ->whereRaw('0 = 1');
        }

        return $this->morphedByMany($modelClass, 'candidate', 'candidate_pool_candidates');
    }
}
