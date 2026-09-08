<?php

use App\Ai\Agents\DataAssistant;
use App\Livewire\AskAssistant;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create();
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

/**
 * Records a conversation the way a real exchange would leave it: the row the
 * package's middleware inserts, plus the industry and agent the component
 * stamps on afterwards.
 */
function storeConversation(array $attributes = [], array $messages = []): Conversation
{
    $conversation = Conversation::create(array_merge([
        'id' => (string) Str::uuid7(),
        'user_id' => test()->user->id,
        'industry_id' => test()->industry->id,
        'agent' => DataAssistant::class,
        'title' => 'Which candidates are Live?',
    ], $attributes));

    foreach ($messages as $index => $message) {
        $conversation->messages()->create([
            'id' => (string) Str::uuid7(),
            'user_id' => $conversation->user_id,
            'agent' => DataAssistant::class,
            'role' => $message['role'],
            'content' => $message['content'],
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);
    }

    return $conversation;
}

test('the last conversation is reopened with its messages when the page loads', function () {
    $conversation = storeConversation(messages: [
        ['role' => 'user', 'content' => 'Which candidates are Live?'],
        ['role' => 'assistant', 'content' => 'Three candidates are Live.'],
    ]);

    Livewire::test(AskAssistant::class)
        ->assertSet('conversationId', $conversation->id)
        ->assertSet('messages.0.content', 'Which candidates are Live?')
        ->assertSet('messages.1.role', 'assistant')
        ->assertSee('Three candidates are Live.');
});

test('the most recently used conversation is the one reopened', function () {
    storeConversation(['updated_at' => now()->subDay(), 'title' => 'Older chat']);
    $newest = storeConversation(['updated_at' => now(), 'title' => 'Newer chat']);

    Livewire::test(AskAssistant::class)->assertSet('conversationId', $newest->id);
});

test('nothing is reopened when the user has no earlier conversation', function () {
    Livewire::test(AskAssistant::class)
        ->assertSet('conversationId', null)
        ->assertSet('messages', []);
});

test('a conversation recorded under another industry is not reopened', function () {
    $otherIndustry = Industry::factory()->create();

    storeConversation(['industry_id' => $otherIndustry->id], [
        ['role' => 'assistant', 'content' => 'Healthcare candidate Jane Doe is Live.'],
    ]);

    Livewire::test(AskAssistant::class)
        ->assertSet('conversationId', null)
        ->assertDontSee('Healthcare candidate Jane Doe is Live.');
});

test('another user\'s conversation is not reopened', function () {
    $otherUser = User::factory()->create();

    storeConversation(['user_id' => $otherUser->id], [
        ['role' => 'assistant', 'content' => 'Riverside School has 3 bookings.'],
    ]);

    Livewire::test(AskAssistant::class)
        ->assertSet('conversationId', null)
        ->assertDontSee('Riverside School has 3 bookings.');
});

test('a conversation belonging to a different agent is not reopened', function () {
    storeConversation(['agent' => 'App\Ai\Agents\SomeOtherAgent']);

    Livewire::test(AskAssistant::class)->assertSet('conversationId', null);
});

test('a conversation predating the industry and agent columns is not reopened', function () {
    storeConversation(['industry_id' => null, 'agent' => null]);

    Livewire::test(AskAssistant::class)->assertSet('conversationId', null);
});

test('a new conversation is stamped with the active industry and agent', function () {
    DataAssistant::fake(['Three candidates are Live.']);

    $component = Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond');

    expect(Conversation::find($component->get('conversationId')))
        ->industry_id->toBe($this->industry->id)
        ->agent->toBe(DataAssistant::class);
});

test('a conversation started in one industry is reopened there but not in another', function () {
    DataAssistant::fake(['Three candidates are Live.']);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'Which candidates are Live?')
        ->call('send')
        ->call('respond');

    Livewire::test(AskAssistant::class)->assertSee('Three candidates are Live.');

    $otherIndustry = Industry::factory()->create();
    Cache::put("user.{$this->user->id}.active_industry", $otherIndustry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $otherIndustry->id);

    Livewire::test(AskAssistant::class)
        ->assertSet('conversationId', null)
        ->assertDontSee('Three candidates are Live.');
});

test('clearing the chat stops that conversation being reopened next time', function () {
    storeConversation(messages: [
        ['role' => 'assistant', 'content' => 'Three candidates are Live.'],
    ]);

    Livewire::test(AskAssistant::class)->call('clearChat');

    Livewire::test(AskAssistant::class)
        ->assertSet('conversationId', null)
        ->assertDontSee('Three candidates are Live.');
});

test('a cleared conversation is kept and still listed in the history panel', function () {
    $conversation = storeConversation(['title' => 'A cleared chat']);

    Livewire::test(AskAssistant::class)->call('clearChat');

    expect(Conversation::whereKey($conversation->id)->exists())->toBeTrue();

    Livewire::test(AskAssistant::class)->assertSee('A cleared chat');
});

test('the history panel lists the user\'s conversations, most recent first', function () {
    storeConversation(['title' => 'Bookings this week', 'updated_at' => now()->subHour()]);
    storeConversation(['title' => 'Clients in Manchester', 'updated_at' => now()]);

    Livewire::test(AskAssistant::class)
        ->assertSeeInOrder(['Clients in Manchester', 'Bookings this week']);
});

test('the history panel does not list conversations from another user or industry', function () {
    $otherUser = User::factory()->create();
    $otherIndustry = Industry::factory()->create();

    storeConversation(['user_id' => $otherUser->id, 'title' => 'Someone else\'s chat']);
    storeConversation(['industry_id' => $otherIndustry->id, 'title' => 'Another sector\'s chat']);

    Livewire::test(AskAssistant::class)
        ->assertDontSee('Someone else\'s chat')
        ->assertDontSee('Another sector\'s chat');
});

test('the history panel is hidden when there is nothing earlier to show', function () {
    Livewire::test(AskAssistant::class)->assertDontSee('Earlier chats');
});

test('picking a conversation from the history panel loads its messages', function () {
    storeConversation(['updated_at' => now(), 'title' => 'Newest chat']);

    $earlier = storeConversation(['updated_at' => now()->subDay(), 'title' => 'Earlier chat'], [
        ['role' => 'user', 'content' => 'Find vacancies in Leicester'],
        ['role' => 'assistant', 'content' => 'Two vacancies are open in Leicester.'],
    ]);

    Livewire::test(AskAssistant::class)
        ->call('loadConversation', $earlier->id)
        ->assertSet('conversationId', $earlier->id)
        ->assertSet('messages.0.content', 'Find vacancies in Leicester')
        ->assertSee('Two vacancies are open in Leicester.');
});

test('loading a conversation clears any stale "show me more" offer', function () {
    $earlier = storeConversation(messages: [
        ['role' => 'assistant', 'content' => 'An answer.'],
    ]);

    Livewire::test(AskAssistant::class)
        ->set('moreResultsAvailable', true)
        ->call('loadConversation', $earlier->id)
        ->assertSet('moreResultsAvailable', false);
});

test('loading a conversation from the history panel makes it the one reopened again', function () {
    $conversation = storeConversation(['dismissed_at' => now(), 'title' => 'A cleared chat']);

    Livewire::test(AskAssistant::class)->call('loadConversation', $conversation->id);

    Livewire::test(AskAssistant::class)->assertSet('conversationId', $conversation->id);
});

test('loading another user\'s conversation returns 404', function () {
    $otherUser = User::factory()->create();
    $conversation = storeConversation(['user_id' => $otherUser->id]);

    Livewire::test(AskAssistant::class)
        ->call('loadConversation', $conversation->id)
        ->assertNotFound();
});

test('loading a conversation from another industry returns 404', function () {
    $otherIndustry = Industry::factory()->create();
    $conversation = storeConversation(['industry_id' => $otherIndustry->id]);

    Livewire::test(AskAssistant::class)
        ->call('loadConversation', $conversation->id)
        ->assertNotFound();
});

test('loading a conversation that does not exist returns 404', function () {
    Livewire::test(AskAssistant::class)
        ->call('loadConversation', (string) Str::uuid7())
        ->assertNotFound();
});

test('continuing a reopened conversation adds to it rather than starting another', function () {
    DataAssistant::fake(['And two are in Manchester.']);

    $conversation = storeConversation(messages: [
        ['role' => 'user', 'content' => 'Which candidates are Live?'],
        ['role' => 'assistant', 'content' => 'Three candidates are Live.'],
    ]);

    Livewire::test(AskAssistant::class)
        ->set('prompt', 'And which of those are in Manchester?')
        ->call('send')
        ->call('respond')
        ->assertSet('conversationId', $conversation->id);

    expect(Conversation::count())->toBe(1);
});
