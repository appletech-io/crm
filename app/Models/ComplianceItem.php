<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named group of one or more Compliance Item Fields — e.g. "DBS" holding a
 * DBS Number (text), Issue Date (date), and Expiry Date (date with expiry
 * tracking) — rather than a single data_type/value itself. See
 * ComplianceItemField for the individual typed fields, and
 * App\Services\Candidates\ComplianceRequirements for how a candidate's
 * required items/fields are resolved.
 */
class ComplianceItem extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $guarded = [];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function jobTitles(): BelongsToMany
    {
        return $this->belongsToMany(JobTitle::class, 'compliance_item_job_titles')
            ->using(ComplianceItemJobTitle::class)
            ->withPivot(['company_id', 'industry_id'])
            ->withTimestamps();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ComplianceItemField::class);
    }
}
