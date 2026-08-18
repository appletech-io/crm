<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Database\Factories\ClientContactJobTitleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContactJobTitle extends Model
{
    /** @use HasFactory<ClientContactJobTitleFactory> */
    use BelongsToCompany;

    use HasFactory;

    protected $guarded = [];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
