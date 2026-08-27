<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\JobTitleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JobTitle extends Model
{
    /** @use HasFactory<JobTitleFactory> */
    use BelongsToCompany;

    use HasFactory;

    protected $guarded = [];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    /**
     * The Compliance Items a generic Candidate applying for this job title
     * must complete — see App\Services\Candidates\ComplianceRequirements.
     */
    public function complianceItems(): BelongsToMany
    {
        return $this->belongsToMany(ComplianceItem::class, 'compliance_item_job_titles')
            ->using(ComplianceItemJobTitle::class)
            ->withPivot(['company_id', 'industry_id'])
            ->withTimestamps();
    }
}
