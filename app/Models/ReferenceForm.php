<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferenceForm extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_statement_only' => 'boolean',
            'needs_position_and_organisation' => 'boolean',
        ];
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ReferenceFormField::class)->orderBy('sort_order');
    }
}
