<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\JobTitleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
