<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasFieldSuggestions;
use App\Models\Traits\HasProviderExternalId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * A candidate for any industry that doesn't need Education/Healthcare's
 * level of bespoke vetting — just basic details, plus whatever Compliance
 * Items their job title requires (see ComplianceRequirements), rather than
 * a fixed set of hardcoded vetting fields.
 */
class Candidate extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasFieldSuggestions;
    use HasProviderExternalId;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'average_rating' => 'float',
        'ratings_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function application(): HasOne
    {
        return $this->hasOne(CandidateApplication::class);
    }

    public function complianceValues(): HasMany
    {
        return $this->hasMany(CandidateComplianceValue::class);
    }

    public function skills(): MorphToMany
    {
        return $this->morphToMany(CandidateSkill::class, 'candidate', 'candidate_skill_candidates');
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

    public function latestStatus(): MorphOne
    {
        return $this->morphOne(CandidateCandidateStatus::class, 'model')->latestOfMany();
    }

    public function currentStatusName(): ?string
    {
        return $this->statuses()->first()?->status?->name;
    }

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
     * can be expressed as an automation condition. Null for a candidate
     * who's never had a booking, so that alone never satisfies a
     * days_since_at_least condition.
     */
    protected function lastBookingDate(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->bookings()
                ->selectRaw('MAX(COALESCE(end_date, start_date)) as last_date')
                ->value('last_date'),
        );
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

    /** @return MorphMany<Booking, $this> */
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'candidate');
    }

    public function payRates(): MorphMany
    {
        return $this->morphMany(PayRate::class, 'model');
    }

    public function formattedCv(): MorphOne
    {
        return $this->morphOne(FormattedCv::class, 'candidate');
    }

    public function candidatePools(): MorphToMany
    {
        return $this->morphToMany(CandidatePool::class, 'candidate', 'candidate_pool_candidates');
    }

    /** @return MorphMany<ProviderError, $this> */
    public function providerErrors(): MorphMany
    {
        return $this->morphMany(ProviderError::class, 'candidate');
    }

    /** @return array<string, array{0: class-string<Model>, 1: array<int, string>}> */
    protected static function relationSuggestions(): array
    {
        return [
            'application' => [CandidateApplication::class, ['candidate_id']],
        ];
    }

    /** @return array<int, string> */
    protected static function toManyRelationSuggestions(): array
    {
        return ['skills'];
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

    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        if (auth()->user()?->isAdmin() || auth()->user()?->hasRole('compliance')) {
            return $query;
        }

        return $query->where('consultant_id', auth()->id());
    }
}
