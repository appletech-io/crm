<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Actions\Clients\EnsureClientCandidatePool;
use App\Jobs\GeocodeClient;
use App\Jobs\SyncPayrollProviderRecord;
use App\Models\Client;

class ClientObserver
{
    public function created(Client $client): void
    {
        EnsureClientCandidatePool::run($client);
    }

    public function saved(Client $client): void
    {
        if ($client->wasChanged('postcode') || ($client->wasRecentlyCreated && filled($client->postcode))) {
            GeocodeClient::dispatch($client);
        }

        CheckActions::run($client);

        SyncPayrollProviderRecord::dispatch($client);
    }

    public function deleted(Client $client): void
    {
        CheckActions::run($client);
    }
}
