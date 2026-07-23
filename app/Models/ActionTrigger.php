<?php

namespace App\Models;

use Database\Factories\ActionTriggerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActionTrigger extends Model
{
    /** @use HasFactory<ActionTriggerFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function todoItem(): BelongsTo
    {
        return $this->belongsTo(TodoItem::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }
}
