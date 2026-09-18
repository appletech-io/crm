<?php

namespace App\Observers;

use App\Enums\TodoPriority;
use App\Filament\Resources\TodoItems\TodoItemResource;
use App\Models\TodoItem;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class TodoItemObserver
{
    public function created(TodoItem $todoItem): void
    {
        if ($todoItem->priority !== TodoPriority::High) {
            return;
        }

        $user = $todoItem->user;

        if (! $user) {
            return;
        }

        Notification::make()
            ->title($todoItem->name)
            ->icon('heroicon-o-exclamation-circle')
            ->iconColor('danger')
            ->body($todoItem->linkedRecordLabel())
            ->actions([
                Action::make('complete')
                    ->label('Complete')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->dispatch('completeTodoItem', [$todoItem->id])
                    ->markAsRead(),
                Action::make('view')
                    ->label('View')
                    ->url(TodoItemResource::getUrl('edit', ['record' => $todoItem]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }

    public function saved(TodoItem $todoItem): void
    {
        if ($todoItem->wasChanged('completed_at') && $todoItem->action_trigger_id) {
            $todoItem->actionTrigger->syncResolution();
        }
    }
}
