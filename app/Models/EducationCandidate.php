<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasFieldSuggestions;
use Database\Factories\EducationCandidateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationCandidate extends Model
{
    /** @use HasFactory<EducationCandidateFactory> */
    use BelongsToCompany;

    use HasFactory;
    use HasFieldSuggestions;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date',
        'availability' => 'array',
        'key_stages' => 'array',
        'education_and_qualification' => 'string',
        'employment_history' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
        'available_from' => 'date',
        'compliance_completed_at' => 'datetime',
        'barred_list_check_date' => 'date',
        'overseas_police_clearance_check_date' => 'date',
        'visa_issue_date' => 'date',
        'visa_expiry_date' => 'date',
        'trn_issue_date' => 'date',
        'safeguarding_certified_date' => 'date',
        'dbs_checked_date' => 'date',
        'proof_of_address_checked_at' => 'datetime',
        'ni_number_checked_at' => 'datetime',
        'update_service_checked_at' => 'datetime',
    ];

    /** @return array<string, array{0: class-string<Model>, 1: array<int, string>}> */
    protected static function relationSuggestions(): array
    {
        return [
            'application' => [EducationApplication::class, ['education_candidate_id']],
            'qualification' => [Qualification::class, []],
        ];
    }

    /** @return array<int, string> */
    protected static function toManyRelationSuggestions(): array
    {
        return ['skills'];
    }

    public function application(): HasOne
    {
        return $this->hasOne(EducationApplication::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        if (auth()->user()?->isAdmin()) {
            return $query;
        }

        return $query->where('consultant_id', auth()->id());
    }

    public function complianceCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'compliance_completed_by');
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class);
    }

    public function skills(): MorphToMany
    {
        return $this->morphToMany(CandidateSkill::class, 'candidate', 'candidate_skill_candidates');
    }

    public function candidatePools(): MorphToMany
    {
        return $this->morphToMany(CandidatePool::class, 'candidate', 'candidate_pool_candidates');
    }

    public function references(): MorphMany
    {
        return $this->morphMany(CandidateReference::class, 'candidate');
    }

    public function employmentHistories(): MorphMany
    {
        return $this->morphMany(CandidateEmploymentHistory::class, 'candidate');
    }

    /** @return MorphMany<CandidateDocument, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(CandidateDocument::class, 'candidate');
    }

    public function statuses(): MorphMany
    {
        return $this->morphMany(CandidateCandidateStatus::class, 'model')->latest();
    }

    public function currentStatusName(): ?string
    {
        return $this->statuses()->first()?->status?->name;
    }

    public function dnuCandidate(): bool
    {
        if ($this->currentStatusName() === 'DNU') {
            return true;
        }

        if ($this->barred_list_check === 'no') {
            return true;
        }

        if ($this->lived_overseas_six_months === 'yes' && $this->overseas_police_clearance_check === 'no') {
            return true;
        }

        return false;
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(CandidateActivity::class, 'model')->latest();
    }

    public function payRates(): MorphMany
    {
        return $this->morphMany(PayRate::class, 'model');
    }
}
