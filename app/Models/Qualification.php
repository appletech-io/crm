<?php

namespace App\Models;

use Database\Factories\QualificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Qualification extends Model
{
    /** @use HasFactory<QualificationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    public function jobTitles(): BelongsToMany
    {
        return $this->belongsToMany(JobTitle::class, 'qualification_job_titles')
            ->using(QualificationJobTitle::class)
            ->withPivot(['company_id', 'industry_id'])
            ->withTimestamps();
    }
}
