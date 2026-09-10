<?php

namespace App\Models;

use App\Enums\ActionAssigneeType;
use App\Enums\ActionEmailRecipient;
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
            'assignee_type' => ActionAssigneeType::class,
            'todo_priority' => TodoPriority::class,
            'email_recipient' => ActionEmailRecipient::class,
            'is_active' => 'boolean',
            'one_off' => 'boolean',
        ];
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function isRoleBased(): bool
    {
        return $this->assignee_type === ActionAssigneeType::Role;
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

    /**
     * Whether this action should be treated as already having fired for this
     * record, and so shouldn't fire again right now. A one-off action never
     * fires twice for the same record, even once its trigger resolves — for
     * everything else, only a currently open (unresolved) trigger blocks it.
     */
    public function hasAlreadyFiredFor(Model $record): bool
    {
        if ($this->one_off) {
            return $this->triggers()
                ->where('model_type', $record->getMorphClass())
                ->where('model_id', $record->getKey())
                ->exists();
        }

        return $this->openTriggerFor($record) !== null;
    }
}
