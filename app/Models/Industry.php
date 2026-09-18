<?php

namespace App\Models;

use Database\Factories\IndustryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Industry extends Model
{
    /** @use HasFactory<IndustryFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * 'generic' is a placeholder slug — any sector that doesn't need
     * Education/Healthcare's bespoke vetting just needs its own entry here
     * pointing at Candidate::class (or more than one entry can share it), no
     * new candidate model/resource/vetting stack required. See
     * App\Services\Candidates\ComplianceRequirements for how that model's
     * compliance requirements are resolved instead of hardcoded fields.
     *
     * @var array<string, class-string<Model>|null>
     */
    protected static array $candidateModelMap = [
        'education' => EducationCandidate::class,
        'healthcare' => HealthcareCandidate::class,
        'generic' => Candidate::class,
        'it' => Candidate::class,
        'construction' => Candidate::class,
    ];

    /** @return class-string<Model>|null */
    public function candidateModel(): ?string
    {
        return static::$candidateModelMap[$this->slug] ?? null;
    }

    /** @return class-string<Model>|null */
    public static function candidateModelForSlug(string $slug): ?string
    {
        return static::$candidateModelMap[$slug] ?? null;
    }

    /** @param class-string<Model> $model */
    public static function slugForCandidateModel(string $model): ?string
    {
        return array_search($model, static::$candidateModelMap, strict: true) ?: null;
    }

    /**
     * Whether more than one industry slug maps to this candidate model (e.g.
     * 'generic', 'construction' and 'it' all currently point at the plain
     * Candidate::class). Callers that scope by candidate_type alone — like
     * Booking::scopeForActiveIndustry() — can't tell those industries apart
     * without an extra check when this is true.
     *
     * @param  class-string<Model>  $model
     */
    public static function candidateModelIsShared(string $model): bool
    {
        return count(array_keys(static::$candidateModelMap, $model, strict: true)) > 1;
    }

    /** @return array<string, array{label: string, type: string}> */
    public function candidateFieldSuggestions(): array
    {
        $model = $this->candidateModel();

        if (! $model || ! method_exists($model, 'candidateFieldSuggestions')) {
            return [];
        }

        return $model::candidateFieldSuggestions();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_industry')
            ->using(CompanyIndustry::class)
            ->withPivot(['uses_bookings', 'uses_perm', 'complex_booking']);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_industry');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
