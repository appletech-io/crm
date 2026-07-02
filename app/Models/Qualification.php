<?php

namespace App\Models;

use Database\Factories\QualificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Qualification extends Model
{
    /** @use HasFactory<QualificationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];
}
