<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Models\Client;
use App\Services\Payroll\Evertime\Concerns\BuildsClientPayloads;
use App\Services\Payroll\Evertime\EvertimeClient;

/**
 * PUT /clients — client-level fields only. Unlike CreateClient's POST, this
 * never touches Contacts/Locations, so it's safe to send on every routine
 * sync of an already-known client without risking anything Evertime
 * already has registered against it.
 */
class UpdateClient
{
    use BuildsClientPayloads;

    public function __construct(private readonly EvertimeClient $client) {}

    public function handle(Client $client, string $clientId, string $contactId, string $locationId): void
    {
        $this->client->put('/clients', [
            'Name' => $client->name,
            'ClientId' => $clientId,
            'MainContactId' => $contactId,
            'DefaultLocationId' => $locationId,
            'Financials' => $this->financialsPayload(),
        ]);
    }
}
