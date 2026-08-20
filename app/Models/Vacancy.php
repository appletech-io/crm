<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\VacancyEmploymentType;
use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasFieldSuggestions;
use Database\Factories\VacancyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Vacancy extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<VacancyFactory> */
    use HasFactory;

    use HasFieldSuggestions;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'salary_min' => Money::class,
            'salary_max' => Money::class,
            'positions_available' => 'integer',
            'employment_type' => VacancyEmploymentType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'placement_fee_percentage' => 'float',
            'open_for_applications' => 'boolean',
            'filled_at' => 'datetime',
        ];
    }

    /**
     * Slugs are global (the public apply URL has no other tenant segment),
     * generated once at creation, and never regenerated on update so
     * previously shared links keep working even if the title changes.
     */
    public static function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'vacancy';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /** @return array<string, array{0: class-string<Model>, 1: array<int, string>}> */
    protected static function relationSuggestions(): array
    {
        return [
            'client' => [Client::class, ['company_id', 'industry_id']],
            'jobTitle' => [JobTitle::class, []],
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
            'placements_filled' => [
                'label' => 'Placements Filled',
                'type' => 'boolean',
            ],
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function jobStatus(): BelongsTo
    {
        return $this->belongsTo(JobStatus::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function filledBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(CandidateSkill::class, 'candidate_skill_vacancies');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(VacancyActivity::class, 'model')->latest();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(VacancyApplication::class)->latest();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(VacancyCandidateMatch::class)->orderByDesc('score');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(VacancyPlacement::class);
    }

    public function isTemp(): bool
    {
        return $this->employment_type === VacancyEmploymentType::Temp;
    }

    /**
     * Whether every open position has a recorded placement.
     */
    public function isFullyPlaced(): bool
    {
        return $this->placements()->count() >= $this->positions_available;
    }

    /**
     * True once every position has a placement and every placed candidate's
     * current status is flagged as a filled placement (CandidateStatus::
     * is_filled_status). Exposed as a "Placements Filled" condition field so
     * a consultant can drive a Job Status Automation off it — e.g. "From:
     * Client Assessment, To: Placed, Condition: Placements Filled = True" —
     * rather than this app forcing any particular target status itself.
     * Re-checked (via VacancyPlacementObserver and
     * CandidateCandidateStatusObserver) whenever a placement or a placed
     * candidate's status changes.
     */
    protected function placementsFilled(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if (! $this->isFullyPlaced()) {
                    return false;
                }

                return $this->placements->every(
                    fn (VacancyPlacement $placement): bool => (bool) $placement->candidate?->latestStatus?->status?->is_filled_status
                );
            },
        );
    }

    /**
     * A rough placement-fee estimate for this vacancy, used to give the
     * client's Pipeline tab a sense of deal size — the midpoint of the
     * salary range (or whichever bound is set) times the fee percentage,
     * times how many positions are open. Returns null rather than a
     * misleading £0 when the salary or fee percentage isn't set yet, or
     * when a partial fill can't be accounted for (there's no per-position
     * fill tracking, so this always assumes every position is still open).
     * Temp roles never produce an estimate here — they're filled via a
     * Booking, not a permanent placement fee.
     */
    public function estimatedPlacementValue(): ?float
    {
        if ($this->isTemp() || $this->placement_fee_percentage === null) {
            return null;
        }

        $salary = match (true) {
            $this->salary_min !== null && $this->salary_max !== null => ($this->salary_min + $this->salary_max) / 2,
            $this->salary_min !== null => $this->salary_min,
            $this->salary_max !== null => $this->salary_max,
            default => null,
        };

        if ($salary === null) {
            return null;
        }

        return $salary * ($this->placement_fee_percentage / 100) * $this->positions_available;
    }

    /**
     * The real placement margin for this vacancy, computed from the actual
     * salary recorded against each placed candidate rather than the salary
     * range estimate estimatedPlacementValue() uses. Temp roles are
     * excluded — their margin comes from the rate/charge-rate spread on the
     * Booking created for them, not from a vacancy-level fee percentage.
     */
    public function actualPlacementValue(): ?float
    {
        if ($this->isTemp() || $this->placement_fee_percentage === null) {
            return null;
        }

        $salaries = $this->placements->pluck('actual_salary')->filter();

        if ($salaries->isEmpty()) {
            return null;
        }

        return $salaries->sum() * ($this->placement_fee_percentage / 100);
    }

    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        $query->forActiveIndustry();

        if (auth()->user()?->isAdmin()) {
            return $query;
        }

        return $query->where('consultant_id', auth()->id());
    }

    /**
     * A vacancy has no industry_id of its own — its industry is inferred
     * from the client it belongs to, the same way Booking infers its
     * industry from the candidate model it's booked for.
     */
    public function scopeForActiveIndustry(Builder $query): Builder
    {
        return $query->whereHas('client', fn (Builder $q) => $q->where('industry_id', active_industry_id()));
    }
}
