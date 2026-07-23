<?php

namespace App\Observers;

use App\Actions\Automations\CheckActions;
use App\Jobs\GeocodeClient;
use App\Models\Client;

class ClientObserver
{
    public function saved(Client $client): void
    {
        if ($client->wasChanged('postcode') || ($client->wasRecentlyCreated && filled($client->postcode))) {
            GeocodeClient::dispatch($client);
        }

        CheckActions::run($client);
    }
}
