<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The company_industry pivot, promoted to a real model so its own
 * uses_bookings flag (App\Filament\Resources\Companies\Schemas\CompanyForm)
 * can be edited per row via a Repeater — a plain BelongsToMany's
 * pivotData() only supports one uniform value across every selected
 * record in a single save, not one that differs per industry.
 */
class CompanyIndustry extends Pivot
{
    public $incrementing = true;

    protected $table = 'company_industry';

    protected function casts(): array
    {
        return [
            'uses_bookings' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
