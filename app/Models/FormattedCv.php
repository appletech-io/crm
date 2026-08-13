<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FormattedCv extends Model
{
    protected $guarded = [];

    public function candidate(): MorphTo
    {
        return $this->morphTo();
    }
}
