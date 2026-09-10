<?php

namespace App\Models;

use Database\Factories\ActionTriggerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function todoItems(): HasMany
    {
        return $this->hasMany(TodoItem::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    /**
     * Recomputes resolved_at from this trigger's own to-dos — resolved
     * (keeping the original resolved_at if already set) as soon as any one
     * of them is completed, reopened if none of them are complete anymore.
     * Driven entirely by a person completing/reopening a to-do, never by
     * re-evaluating the action's conditions — see CheckActions::handle().
     */
    public function syncResolution(): void
    {
        $isResolved = $this->todoItems()->whereNotNull('completed_at')->exists();

        $this->update([
            'resolved_at' => $isResolved ? ($this->resolved_at ?? now()) : null,
        ]);
    }
}
