<?php

namespace App\Models;

use App\Enums\CandidateAvailabilityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CandidateAvailability extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'status' => CandidateAvailabilityStatus::class,
    ];

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }
}
