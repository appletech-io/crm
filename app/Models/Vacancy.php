<?php

namespace App\Models;

use App\Casts\Money;
use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasFieldSuggestions;
use Database\Factories\VacancyFactory;
use Illuminate\Database\Eloquent\Builder;
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
