<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

function makeConversation(string $updatedAt): Conversation
{
    $conversation = Conversation::create([
        'id' => (string) Str::uuid(),
        'title' => 'Test conversation',
    ]);

    // Eloquent re-touches updated_at on save, so it's backdated afterwards
    // via a raw query rather than passed to create()/update() directly.
    DB::table('agent_conversations')->where('id', $conversation->id)->update(['updated_at' => $updatedAt]);

    return $conversation->fresh();
}

function makeMessage(Conversation $conversation): ConversationMessage
{
    return ConversationMessage::create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'agent' => 'test-agent',
        'role' => 'user',
        'content' => 'Hello',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
    ]);
}

test('a conversation with no activity in over 30 days is deleted, along with its messages', function () {
    $stale = makeConversation(now()->subDays(31)->toDateTimeString());
    $message = makeMessage($stale);

    $this->artisan('ai:prune-conversations')->assertSuccessful();

    $this->assertDatabaseMissing('agent_conversations', ['id' => $stale->id]);
    $this->assertDatabaseMissing('agent_conversation_messages', ['id' => $message->id]);
});

test('a conversation active within the last 30 days is left alone', function () {
    $recent = makeConversation(now()->subDays(29)->toDateTimeString());
    $message = makeMessage($recent);

    $this->artisan('ai:prune-conversations')->assertSuccessful();

    $this->assertDatabaseHas('agent_conversations', ['id' => $recent->id]);
    $this->assertDatabaseHas('agent_conversation_messages', ['id' => $message->id]);
});

test('only the stale conversations are removed when stale and recent ones both exist', function () {
    $stale = makeConversation(now()->subDays(45)->toDateTimeString());
    $alsoStale = makeConversation(now()->subDays(31)->toDateTimeString());
    $recent = makeConversation(now()->subDays(5)->toDateTimeString());

    $this->artisan('ai:prune-conversations')->assertSuccessful();

    $this->assertDatabaseMissing('agent_conversations', ['id' => $stale->id]);
    $this->assertDatabaseMissing('agent_conversations', ['id' => $alsoStale->id]);
    $this->assertDatabaseHas('agent_conversations', ['id' => $recent->id]);
});
