<?php

use App\Livewire\TodoItemNotificationActions;
use App\Models\TodoItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('consultant');
    $this->actingAs($this->user);
});

test('creating a high priority to-do sends the owner a database notification with a Complete action', function () {
    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'high',
        'name' => 'Chase reference urgently',
    ]);

    $notification = $this->user->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Chase reference urgently');

    $actionLabels = collect($notification->data['actions'])->pluck('label');
    expect($actionLabels)->toContain('Complete')->toContain('View');
});

test('creating a low or medium priority to-do sends no notification', function () {
    TodoItem::factory()->create(['user_id' => $this->user->id, 'priority' => 'low']);
    TodoItem::factory()->create(['user_id' => $this->user->id, 'priority' => 'medium']);

    expect($this->user->notifications()->count())->toBe(0);
});

test('completing a todo via the notification action listener marks it done', function () {
    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'high',
    ]);

    expect($todoItem->isComplete())->toBeFalse();

    Livewire::test(TodoItemNotificationActions::class)
        ->call('complete', $todoItem->id);

    expect($todoItem->fresh()->isComplete())->toBeTrue();
});

test('the notification action listener cannot complete another user\'s todo', function () {
    $otherUser = User::factory()->create();
    $theirs = TodoItem::factory()->create([
        'user_id' => $otherUser->id,
        'priority' => 'high',
    ]);

    Livewire::test(TodoItemNotificationActions::class)
        ->call('complete', $theirs->id);

    expect($theirs->fresh()->isComplete())->toBeFalse();
});

test('completing an already-complete todo via the listener is a no-op', function () {
    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'high',
        'completed_at' => now()->subDay(),
    ]);
    $originalCompletedAt = $todoItem->completed_at;

    Livewire::test(TodoItemNotificationActions::class)
        ->call('complete', $todoItem->id);

    expect($todoItem->fresh()->completed_at->equalTo($originalCompletedAt))->toBeTrue();
});
