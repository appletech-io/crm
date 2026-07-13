<?php

namespace App\Models;

use App\Casts\Money;
use App\Models\Traits\BelongsToCompany;
use Database\Factories\EducationBookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationBooking extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<EducationBookingFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hourly_rate' => Money::class,
            'day_rate' => Money::class,
            'half_day_rate' => Money::class,
            'hourly_charge_rate' => Money::class,
            'day_charge_rate' => Money::class,
            'half_day_charge_rate' => Money::class,
            'day_periods' => 'array',
        ];
    }

    public function education_client(): BelongsTo
    {
        return $this->belongsTo(EducationClient::class);
    }

    public function education_candidate(): BelongsTo
    {
        return $this->belongsTo(EducationCandidate::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }
}
