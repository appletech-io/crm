<?php

namespace App\Models;

use App\Casts\Money;
use App\Models\Traits\BelongsToCompany;
use Database\Factories\PayRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayRate extends Model
{
    /** @use HasFactory<PayRateFactory> */
    use BelongsToCompany;

    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hourly_rate' => Money::class,
            'day_rate' => Money::class,
            'half_day_rate' => Money::class,
        ];
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }
}
