<?php

namespace App\Models;

use App\Enums\Healthcare\Wellbeing;
use App\Models\Traits\BelongsToCompany;
use Database\Factories\CareLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareLog extends Model
{
    /** @use HasFactory<CareLogFactory> */
    use BelongsToCompany;

    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'wellbeing' => Wellbeing::class,
            'incidents_occurred' => 'boolean',
            'medication_administered' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function bookingDay(): BelongsTo
    {
        return $this->belongsTo(BookingDay::class);
    }
}
