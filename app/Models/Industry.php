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

    /** @var array<string, class-string<Model>|null> */
    protected static array $candidateModelMap = [
        'education' => EducationCandidate::class,
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

    /** @return array<int, string> */
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
        return $this->belongsToMany(Company::class, 'company_industry');
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
