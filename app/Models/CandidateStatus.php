<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\CandidateStatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidateStatus extends Model
{
    /** @use HasFactory<CandidateStatusFactory> */
    use BelongsToCompany;

    use HasFactory;

    protected $guarded = [];

    public static function colorForName(string $name): string
    {
        return match (strtolower(trim($name))) {
            'live' => 'success',
            'onboarding' => 'warning',
            'offline' => 'gray',
            'dnu', 'do not use' => 'danger',
            default => 'primary',
        };
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CandidateCandidateStatus::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(CandidateStatusAutomation::class);
    }
}
