<?php

namespace App\Models;

use Database\Factories\ConsultantKpiTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultantKpiTarget extends Model
{
    /** @use HasFactory<ConsultantKpiTargetFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gp_target' => 'integer',
            'candidate_days_target' => 'integer',
            'working_candidates_target' => 'integer',
            'clients_booked_target' => 'integer',
            'rebook_rate_target' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
