<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Models\Client;
use App\Models\ClientContact;
use App\Services\Payroll\Evertime\Concerns\BuildsClientPayloads;
use App\Services\Payroll\Evertime\EvertimeClient;

/**
 * POST /clients — an entire overwrite of the client's Contacts and
 * Locations (see Evertime_API_Documentationv0.34.pdf), so only safe to use
 * for a client Evertime has never seen before. EvertimeProvider::upsertClient()
 * is what decides that; this class only builds and sends the payload.
 */
class CreateClient
{
    use BuildsClientPayloads;

    public function __construct(private readonly EvertimeClient $client) {}

    public function handle(Client $client, string $clientId, string $locationId, string $contactId, ?ClientContact $contact): void
    {
        $this->client->post('/clients', [
            'Name' => $client->name,
            'ClientId' => $clientId,
            'Locations' => [$this->locationPayload($client, $locationId)],
            'Contacts' => [$this->contactPayload($client, $contact, $contactId, default: true)],
            'Financials' => $this->financialsPayload(),
        ]);
    }
}
