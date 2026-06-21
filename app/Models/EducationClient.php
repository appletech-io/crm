<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationClient extends Model
{
    /** @use HasFactory<\Database\Factories\EducationClientFactory> */
    use BelongsToCompany;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];
}
