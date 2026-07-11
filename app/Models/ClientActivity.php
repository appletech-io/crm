<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClientActivity extends Model
{
    protected $fillable = [
        'user_id',
        'model_type',
        'model_id',
        'type',
        'note',
        'subject',
        'body',
        'contacted',
    ];

    protected $casts = [
        'type' => ActivityType::class,
        'contacted' => 'boolean',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
