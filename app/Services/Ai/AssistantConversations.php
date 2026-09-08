<?php

namespace App\Services\Ai;

use App\Ai\Agents\DataAssistant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

/**
 * Reads and writes the Ask Assistant's stored conversations, always scoped to
 * one user's own history within the industry currently active for them.
 *
 * The scoping is the whole point of this class. Every Ask Assistant tool
 * filters by active_industry()/active_industry_id(), so a transcript belongs
 * to the industry it was recorded under; resuming one under a different
 * industry would put that industry's records back on screen and back into the
 * model's context as conversation history. Keeping every read behind one
 * scoped query means there is no path that can return a conversation the
 * current user should not be reading right now.
 */
class AssistantConversations
{
    /**
     * How many past conversations the history list offers. Deliberately short
     * — this is a "pick up where I left off" list, not an archive browser.
     */
    private const HISTORY_LIMIT = 20;

    /**
     * Get the conversation to reopen automatically, if there is one.
     */
    public function latestFor(User $user): ?Conversation
    {
        return $this->scoped($user)->whereNull('dismissed_at')->first();
    }

    /**
     * Get the user's recent conversations, most recently used first.
     *
     * @return Collection<int, Conversation>
     */
    public function historyFor(User $user): Collection
    {
        return $this->scoped($user)->limit(self::HISTORY_LIMIT)->get();
    }

    /**
     * Find one of the user's own conversations by ID, or null when it does not
     * exist, belongs to someone else, or belongs to another industry or agent.
     */
    public function find(User $user, string $conversationId): ?Conversation
    {
        return $this->scoped($user)->whereKey($conversationId)->first();
    }

    /**
     * Record the industry and agent a newly created conversation belongs to.
     *
     * laravel/ai's RememberConversation middleware inserts the conversation
     * row itself, during the prompt, so these columns can only be stamped on
     * afterwards — until they are, the conversation matches no scoped query.
     */
    public function stampScope(string $conversationId): void
    {
        Conversation::whereKey($conversationId)->update([
            'industry_id' => active_industry_id(),
            'agent' => DataAssistant::class,
        ]);
    }

    /**
     * Stop a conversation being reopened automatically. It stays in the
     * history list, so the user can still go back to it deliberately.
     */
    public function dismiss(string $conversationId): void
    {
        Conversation::whereKey($conversationId)->update(['dismissed_at' => now()]);
    }

    /**
     * Mark a conversation as the one to reopen again, undoing a dismissal.
     */
    public function markResumed(string $conversationId): void
    {
        Conversation::whereKey($conversationId)->update(['dismissed_at' => null]);
    }

    /**
     * Build the message list the chat window renders, in the order it was
     * said. Message IDs are UUIDv7, so ordering by ID is chronological.
     *
     * The package's message model declares no properties and resolves its
     * table at runtime, so attributes are read through getAttribute() rather
     * than magic property access, which static analysis cannot verify.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function transcript(Conversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get(['id', 'role', 'content'])
            ->map(fn (ConversationMessage $message): array => [
                'role' => (string) $message->getAttribute('role'),
                'content' => (string) $message->getAttribute('content'),
            ])
            ->filter(fn (array $message): bool => trim($message['content']) !== '')
            ->values()
            ->all();
    }

    /**
     * Constrain a query to the given user's conversations for the active
     * industry and this agent. With no active industry the industry_id
     * conditions contradict each other, so nothing is ever returned.
     *
     * @return Builder<Conversation>
     */
    private function scoped(User $user): Builder
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('industry_id')
            ->where('industry_id', active_industry_id())
            ->where('agent', DataAssistant::class)
            ->latest('updated_at');
    }
}
