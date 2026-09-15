<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VacancyApplication extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'shortlisted_at' => 'datetime',
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }

    public function jobStatus(): BelongsTo
    {
        return $this->belongsTo(JobStatus::class);
    }

    public function isShortlisted(): bool
    {
        return $this->shortlisted_at !== null;
    }
}
