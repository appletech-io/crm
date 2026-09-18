<?php

use App\Filament\Resources\TodoItems\TodoItemResource;
use App\Models\Industry;
use App\Models\TodoItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('consultant');
    $this->actingAs($this->user);

    $industry = Industry::factory()->create();
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);
});

test('the medium and low priority quick links appear next to the search bar, linking to their tab', function () {
    $indexUrl = TodoItemResource::getUrl('index');

    $this->get('/crm')
        ->assertOk()
        ->assertSeeHtml(e($indexUrl.'?tab=medium'))
        ->assertSeeHtml(e($indexUrl.'?tab=low'));
});

test('the quick link badges only count the current users outstanding medium and low to-dos', function () {
    $otherUser = User::factory()->create();
    TodoItem::factory()->create(['user_id' => $this->user->id, 'priority' => 'medium']);
    TodoItem::factory()->create(['user_id' => $this->user->id, 'priority' => 'medium']);
    TodoItem::factory()->create(['user_id' => $this->user->id, 'priority' => 'low']);
    // Should not count: completed, high priority, or another user's to-do.
    TodoItem::factory()->create(['user_id' => $this->user->id, 'priority' => 'medium', 'completed_at' => now()]);
    TodoItem::factory()->create(['user_id' => $this->user->id, 'priority' => 'high']);
    TodoItem::factory()->create(['user_id' => $otherUser->id, 'priority' => 'medium']);

    $html = view('filament.todo-priority-quick-links')->render();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $mediumLink = $xpath->query("//a[contains(@href, 'tab=medium')]")->item(0);
    $lowLink = $xpath->query("//a[contains(@href, 'tab=low')]")->item(0);

    expect(trim($mediumLink->textContent))->toBe('2')
        ->and(trim($lowLink->textContent))->toBe('1');
});

test('no badge is shown for a priority with no outstanding to-dos', function () {
    $html = view('filament.todo-priority-quick-links')->render();

    expect(trim(strip_tags($html)))->toBe('');
});
