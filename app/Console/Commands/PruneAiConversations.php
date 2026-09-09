<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

#[Signature('ai:prune-conversations')]
#[Description('Delete Ask Assistant conversations, and their messages, that have had no activity in the last 30 days')]
class PruneAiConversations extends Command
{
    private const int MAX_AGE_DAYS = 30;

    public function handle(): int
    {
        $cutoff = now()->subDays(self::MAX_AGE_DAYS);

        Conversation::query()
            ->where('updated_at', '<', $cutoff)
            ->pluck('id')
            ->chunk(500)
            ->each(function ($ids): void {
                // Messages first: no FK/cascade links the two tables, so a
                // conversation deleted first (or a run interrupted between
                // the two deletes) would leave orphaned messages pointing at
                // nothing rather than just an empty, harmless conversation.
                ConversationMessage::query()->whereIn('conversation_id', $ids)->delete();
                Conversation::query()->whereIn('id', $ids)->delete();
            });

        return self::SUCCESS;
    }
}
