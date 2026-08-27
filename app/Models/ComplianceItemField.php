<?php

namespace App\Models;

use App\Enums\ComplianceItemDataType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceItemField extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_type' => ComplianceItemDataType::class,
        ];
    }

    public function complianceItem(): BelongsTo
    {
        return $this->belongsTo(ComplianceItem::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CandidateComplianceValue::class);
    }
}
