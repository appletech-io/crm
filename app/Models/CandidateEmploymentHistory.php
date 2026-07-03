<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CandidateEmploymentHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'worked_from' => 'date',
        'worked_to' => 'date',
    ];

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }
}
