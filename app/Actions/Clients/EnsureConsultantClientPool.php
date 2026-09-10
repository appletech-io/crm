<?php

namespace App\Actions\Clients;

use App\Models\ClientPool;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class EnsureConsultantClientPool
{
    use AsAction;

    /**
     * Every consultant has exactly one main pool per industry they work in —
     * created the moment it's first needed. This is what drives which
     * clients they can see (see Client::scopeVisibleToCurrentUser()).
     */
    public function handle(User $consultant, int $industryId): ClientPool
    {
        return ClientPool::query()->firstOrCreate(
            [
                'user_id' => $consultant->id,
                'industry_id' => $industryId,
                'is_primary' => true,
            ],
            [
                'company_id' => $consultant->company_id,
                'name' => "{$consultant->name}'s Clients",
            ],
        );
    }
}
