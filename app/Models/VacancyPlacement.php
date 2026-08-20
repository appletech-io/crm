<?php

namespace App\Models;

use App\Casts\Money;
use Database\Factories\VacancyPlacementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VacancyPlacement extends Model
{
    /** @use HasFactory<VacancyPlacementFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'actual_salary' => Money::class,
            'placed_at' => 'datetime',
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
}
