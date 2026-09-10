<?php

namespace App\Observers;

use App\Models\TodoItem;

class TodoItemObserver
{
    public function saved(TodoItem $todoItem): void
    {
        if ($todoItem->wasChanged('completed_at') && $todoItem->action_trigger_id) {
            $todoItem->actionTrigger->syncResolution();
        }
    }
}
