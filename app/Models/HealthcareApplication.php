<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HealthcareApplication extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_on' => 'date',
        'completed_at' => 'datetime',
        'email_verified' => 'boolean',
        'cv_parsed_data' => 'array',
    ];

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }
}
