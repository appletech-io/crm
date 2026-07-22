<?php

use App\Livewire\HighPriorityTodoNotifications;
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

test('shows only the current users incomplete high priority todos', function () {
    $mine = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'high',
        'name' => 'Chase reference urgently',
    ]);

    $lowPriority = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'low',
        'name' => 'Tidy up notes',
    ]);

    $completed = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'high',
        'name' => 'Already done task',
        'completed_at' => now(),
    ]);

    $otherUser = User::factory()->create();
    $otherUser->assignRole('consultant');
    $theirs = TodoItem::factory()->create([
        'user_id' => $otherUser->id,
        'priority' => 'high',
        'name' => 'Someone elses urgent task',
    ]);

    $todos = Livewire::test(HighPriorityTodoNotifications::class)->todos;

    expect($todos->pluck('id'))->toContain($mine->id)
        ->not->toContain($lowPriority->id)
        ->not->toContain($completed->id)
        ->not->toContain($theirs->id);
});

test('the topbar renders a link to a high priority todo', function () {
    $todo = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'priority' => 'high',
        'name' => 'Chase reference urgently',
    ]);

    $this->get('/crm/todo-items')
        ->assertOk()
        ->assertSee('Chase reference urgently')
        ->assertSee(route('filament.admin.resources.todo-items.edit', ['record' => $todo]), false);
});

test('site_admin never sees the high priority todo notifications', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    TodoItem::factory()->create([
        'user_id' => $siteAdmin->id,
        'priority' => 'high',
        'name' => 'Should never be visible',
    ]);

    $todos = Livewire::test(HighPriorityTodoNotifications::class)->todos;

    expect($todos)->toBeEmpty();
});
