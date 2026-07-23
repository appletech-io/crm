<?php

namespace App\Models;

use App\Enums\TodoPriority;
use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\EvaluatesConditions;
use Database\Factories\ActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Action extends Model
{
    use BelongsToCompany;
    use EvaluatesConditions;

    /** @use HasFactory<ActionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'todo_priority' => TodoPriority::class,
            'is_active' => 'boolean',
        ];
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(ActionTrigger::class);
    }

    /**
     * The currently-open trigger for this record, if this action fired for it
     * and the condition that caused it hasn't resolved since. Null if it has
     * never fired, or its last firing has already resolved — either way, the
     * action is free to fire again.
     */
    public function openTriggerFor(Model $record): ?ActionTrigger
    {
        return $this->triggers()
            ->open()
            ->where('model_type', $record->getMorphClass())
            ->where('model_id', $record->getKey())
            ->first();
    }
}
