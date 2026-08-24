<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Models\Client;
use App\Services\Payroll\Evertime\Concerns\BuildsClientPayloads;
use App\Services\Payroll\Evertime\EvertimeClient;

/**
 * POST /clients/locations — additive; updates the existing location or
 * adds a new one, without touching any other location Evertime already
 * has for the client (unlike CreateClient's destructive /clients POST).
 */
class UpsertClientLocation
{
    use BuildsClientPayloads;

    public function __construct(private readonly EvertimeClient $client) {}

    public function handle(Client $client, string $clientId, string $locationId): void
    {
        $this->client->post('/clients/locations', [
            'ClientId' => $clientId,
            'Locations' => [$this->locationPayload($client, $locationId)],
        ]);
    }
}
