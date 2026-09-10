<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncClientConsultantPool
{
    use AsAction;

    /**
     * Keeps a client's main-pool membership in sync with its consultant_id —
     * called whenever that column is set or changes, so consultant_id stays
     * the one place staff assign a client, while pool membership (what
     * actually drives visibility) just follows along underneath.
     */
    public function handle(Client $client): void
    {
        $stalePools = $client->pools()->where('is_primary', true)->get()
            ->reject(fn ($pool): bool => $client->consultant_id && $pool->user_id === $client->consultant_id);

        if ($stalePools->isNotEmpty()) {
            $client->pools()->detach($stalePools->pluck('id'));
        }

        if (! $client->consultant_id) {
            return;
        }

        // Fetched fresh rather than via the client's own consultant()
        // relation — that relation may already be cached to the previous
        // consultant from an earlier call in the same request, and Eloquent
        // relation caching doesn't invalidate itself when consultant_id
        // changes underneath it.
        $consultant = User::findOrFail($client->consultant_id);

        $pool = EnsureConsultantClientPool::run($consultant, $client->industry_id);

        $client->pools()->syncWithoutDetaching($pool);
    }
}
