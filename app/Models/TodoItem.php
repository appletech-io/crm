<?php

namespace App\Models;

use App\Enums\TodoPriority;
use Database\Factories\TodoItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property TodoPriority $priority
 * @property Carbon|null $completed_at
 * @property string|null $model_type
 * @property int|null $model_id
 */
class TodoItem extends Model
{
    /** @use HasFactory<TodoItemFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'priority' => TodoPriority::class,
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TodoItem $todoItem) {
            if (! $todoItem->user_id && auth()->hasUser()) {
                $todoItem->user_id = auth()->user()->id;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOwnedByCurrentUser(Builder $query): Builder
    {
        return $query->where('user_id', auth()->id());
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function linkedRecordLabel(): ?string
    {
        return match (true) {
            $this->model === null => null,
            $this->model instanceof Client => $this->model->name,
            $this->model instanceof Booking => "Booking #{$this->model->id}",
            default => trim("{$this->model->first_name} {$this->model->last_name}"),
        };
    }
}
