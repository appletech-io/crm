<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\SampleProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleProfile extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<SampleProfileFactory> */
    use HasFactory;

    protected $guarded = [];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
