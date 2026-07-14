<?php

namespace App\Models;

use App\Enums\BookingDayPeriod;
use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationBookingDayPeriod extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'period' => BookingDayPeriod::class,
        ];
    }

    public function educationBooking(): BelongsTo
    {
        return $this->belongsTo(EducationBooking::class);
    }
}
