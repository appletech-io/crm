<?php

namespace App\Livewire;

use App\Enums\TodoPriority;
use App\Filament\Resources\TodoItems\TodoItemResource;
use App\Models\TodoItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class HighPriorityTodoNotifications extends Component
{
    /** @return Collection<int, TodoItem> */
    #[Computed]
    public function todos(): Collection
    {
        if (! TodoItemResource::canViewAny()) {
            return new Collection;
        }

        return TodoItem::query()
            ->where('user_id', Auth::id())
            ->where('priority', TodoPriority::High)
            ->whereNull('completed_at')
            ->latest()
            ->limit(10)
            ->get();
    }
}
