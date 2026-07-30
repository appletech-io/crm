<?php

namespace App\Models;

use App\Enums\EmailTemplateAudience;
use App\Enums\EmailTemplateType;
use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => EmailTemplateType::class,
            'audience' => EmailTemplateAudience::class,
        ];
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('type', EmailTemplateType::Custom->value);
    }

    public function scopeForAudience(Builder $query, EmailTemplateAudience $audience): Builder
    {
        return $query->whereIn('audience', [$audience->value, EmailTemplateAudience::Both->value]);
    }
}
