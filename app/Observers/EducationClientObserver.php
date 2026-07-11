<?php

namespace App\Observers;

use App\Jobs\GeocodeEducationClient;
use App\Models\EducationClient;

class EducationClientObserver
{
    public function saved(EducationClient $client): void
    {
        if ($client->wasChanged('postcode') || ($client->wasRecentlyCreated && filled($client->postcode))) {
            GeocodeEducationClient::dispatch($client);
        }
    }
}
