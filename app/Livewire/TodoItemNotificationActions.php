<?php

namespace App\Livewire;

use App\Models\TodoItem;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A database notification's actions can only open a URL, mark as read, or
 * dispatch a Livewire event (see Filament's notification docs) — there's no
 * way to run arbitrary code inline. This always-mounted, invisible
 * component is the listener that event dispatches to, so a "Complete"
 * button on a to-do notification (see TodoItemObserver::created()) can
 * actually mark the to-do done rather than just closing the notification.
 */
class TodoItemNotificationActions extends Component
{
    #[On('completeTodoItem')]
    public function complete(int $todoItemId): void
    {
        $todoItem = TodoItem::query()
            ->where('user_id', Auth::id())
            ->find($todoItemId);

        if (! $todoItem || $todoItem->isComplete()) {
            return;
        }

        $todoItem->update(['completed_at' => now()]);

        Notification::make()
            ->success()
            ->title('To-do completed')
            ->send();
    }

    public function render()
    {
        return view('livewire.todo-item-notification-actions');
    }
}
