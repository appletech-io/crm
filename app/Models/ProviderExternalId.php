<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProviderExternalId extends Model
{
    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
