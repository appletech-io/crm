<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasCandidateFieldSuggestions;
use Database\Factories\HealthcareCandidateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HealthcareCandidate extends Model
{
    /** @use HasFactory<HealthcareCandidateFactory> */
    use BelongsToCompany;

    use HasCandidateFieldSuggestions;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date',
        'availability' => 'array',
        'care_settings' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'available_from' => 'date',
        'compliance_completed_at' => 'datetime',
        'professional_registration_checked_at' => 'date',
        'visa_issue_date' => 'date',
        'visa_expiry_date' => 'date',
        'dbs_checked_date' => 'date',
        'proof_of_address_checked_at' => 'datetime',
        'ni_number_checked_at' => 'datetime',
        'update_service_checked_at' => 'datetime',
    ];

    protected static function applicationModelClass(): string
    {
        return HealthcareApplication::class;
    }

    /** @return array<int, string> */
    protected static function applicationExcludedColumns(): array
    {
        return ['candidate_type', 'candidate_id'];
    }

    public function application(): MorphOne
    {
        return $this->morphOne(HealthcareApplication::class, 'candidate');
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
        return $this->currentStatusName() === 'DNU';
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(CandidateActivity::class, 'model');
    }

    public function payRates(): MorphMany
    {
        return $this->morphMany(PayRate::class, 'model');
    }
}
