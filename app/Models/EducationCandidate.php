<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasFieldSuggestions;
use App\Models\Traits\HasProviderExternalId;
use Database\Factories\EducationCandidateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class EducationCandidate extends Model
{
    /** @use HasFactory<EducationCandidateFactory> */
    use BelongsToCompany;

    use HasFactory;
    use HasFieldSuggestions;
    use HasProviderExternalId;
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
        'average_rating' => 'float',
        'ratings_count' => 'integer',
        'available_from' => 'date',
        'payment_method' => PaymentMethod::class,
        'compliance_completed_at' => 'datetime',
        'barred_list_check_date' => 'date',
        'overseas_police_clearance_check_date' => 'date',
        'visa_issue_date' => 'date',
        'visa_expiry_date' => 'date',
        'trn_issue_date' => 'date',
        'safeguarding_certified_date' => 'date',
        'safeguarding_expiry_date' => 'date',
        'benedicts_law_issue_date' => 'date',
        'benedicts_law_expiry_date' => 'date',
        'dbs_checked_date' => 'date',
        'dbs_expiry_date' => 'date',
        'right_to_work_expiry_date' => 'date',
        'right_to_work_checked_date' => 'date',
        'proof_of_address_checked_at' => 'datetime',
        'ni_number_checked_at' => 'datetime',
        'update_service_checked_at' => 'datetime',
        'reference_checked_at' => 'datetime',
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

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class);
    }

    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        if (auth()->user()?->isAdmin() || auth()->user()?->hasRole('compliance')) {
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

    public function formattedCv(): MorphOne
    {
        return $this->morphOne(FormattedCv::class, 'candidate');
    }

    public function statuses(): MorphMany
    {
        return $this->morphMany(CandidateCandidateStatus::class, 'model')->latest();
    }

    /**
     * The candidate's single current status, as opposed to `statuses()` which
     * is their full history — used wherever "is this candidate currently X"
     * needs to be checked in a query (e.g. filtering for Live candidates),
     * since a plain whereHas('statuses.status', ...) would match anyone who
     * was ever in that status, not just their latest one.
     */
    public function latestStatus(): MorphOne
    {
        return $this->morphOne(CandidateCandidateStatus::class, 'model')->latestOfMany();
    }

    public function currentStatusName(): ?string
    {
        return $this->statuses()->first()?->status?->name;
    }

    /**
     * Exposes the current status as a virtual "current_status" attribute so
     * it can be used as an automation condition field — e.g. excluding
     * candidates in a given status from an expiry reminder.
     */
    protected function currentStatus(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->currentStatusName(),
        );
    }

    /**
     * The end (or, for an open-ended booking, start) date of this
     * candidate's most recent booking — exposed as a virtual
     * "last_booking_date" attribute so "no booking in the last N months"
     * can be expressed with the existing days_since_at_least condition,
     * the same way an expiry date is. Null for a candidate who's never had
     * a booking, so — deliberately — that alone never satisfies a
     * days_since_at_least condition; "never booked" isn't the same claim
     * as "was booked, but a while ago".
     */
    protected function lastBookingDate(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->bookings()
                ->selectRaw('MAX(COALESCE(end_date, start_date)) as last_date')
                ->value('last_date'),
        );
    }

    /** @return array<string, array{label: string, type: string, options?: array<string, string>}> */
    protected static function computedFieldSuggestions(): array
    {
        return [
            'current_status' => [
                'label' => 'Current Status',
                'type' => 'select',
                'options' => CandidateStatus::query()
                    ->where('company_id', Auth::user()?->company_id)
                    ->where('industry_id', active_industry_id())
                    ->orderBy('name')
                    ->pluck('name', 'name')
                    ->all(),
            ],
            'last_booking_date' => [
                'label' => 'Last Booking Date',
                'type' => 'date',
            ],
        ];
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

    public function vacancyApplications(): MorphMany
    {
        return $this->morphMany(VacancyApplication::class, 'candidate')->latest();
    }

    /** @return MorphMany<CandidateAvailability, $this> */
    public function availabilities(): MorphMany
    {
        return $this->morphMany(CandidateAvailability::class, 'candidate');
    }

    public function payRates(): MorphMany
    {
        return $this->morphMany(PayRate::class, 'model');
    }

    /** @return MorphMany<Booking, $this> */
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'candidate');
    }

    /** @return MorphMany<ProviderError, $this> */
    public function providerErrors(): MorphMany
    {
        return $this->morphMany(ProviderError::class, 'candidate');
    }
}
