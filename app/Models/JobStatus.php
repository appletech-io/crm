<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\JobStatusFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobStatus extends Model
{
    /** @use HasFactory<JobStatusFactory> */
    use BelongsToCompany;

    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_filled_status' => 'boolean',
        ];
    }

    /**
     * @var array<string, string>
     */
    public const array COLOR_OPTIONS = [
        'red' => 'Red',
        'orange' => 'Orange',
        'amber' => 'Amber',
        'yellow' => 'Yellow',
        'lime' => 'Lime',
        'green' => 'Green',
        'emerald' => 'Emerald',
        'teal' => 'Teal',
        'cyan' => 'Cyan',
        'sky' => 'Sky',
        'blue' => 'Blue',
        'indigo' => 'Indigo',
        'violet' => 'Violet',
        'purple' => 'Purple',
        'fuchsia' => 'Fuchsia',
        'pink' => 'Pink',
        'rose' => 'Rose',
        'gray' => 'Gray',
    ];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(JobStatusAutomation::class);
    }

    /**
     * The order a site admin has deliberately arranged these in (see
     * JobStatusesTable's ->reorderable('sort_order')) — every caller that
     * displays statuses in a left-to-right/top-to-bottom sequence (the Job
     * Pipeline Flow widget, the Job Pipeline report chart) should use this
     * rather than default id order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
