<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationApplication extends Model
{
    protected $fillable = [
        'education_candidate_id',
        'email',
        'status',
        'token',
        'expires_on',
        'completed_at',
    ];

    protected $casts = [
        'expires_on' => 'date',
        'completed_at' => 'datetime',
        'email_verified_at' => 'boolean',
    ];
}
