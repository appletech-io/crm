<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\BookingStatus;
use App\Models\Traits\BelongsToCompany;
use Database\Factories\EducationBookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => BookingStatus::class,
            'hourly_rate' => Money::class,
            'day_rate' => Money::class,
            'half_day_rate' => Money::class,
            'hourly_charge_rate' => Money::class,
            'day_charge_rate' => Money::class,
            'half_day_charge_rate' => Money::class,
        ];
    }

    public function education_client(): BelongsTo
    {
        return $this->belongsTo(EducationClient::class);
    }

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function dayPeriods(): HasMany
    {
        return $this->hasMany(EducationBookingDayPeriod::class)->orderBy('date');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function scopeVisibleToCurrentUser(Builder $query): Builder
    {
        if (auth()->user()?->isAdmin()) {
            return $query;
        }

        return $query->where('consultant_id', auth()->id());
    }
}
