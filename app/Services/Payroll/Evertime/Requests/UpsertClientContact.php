<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Models\Client;
use App\Models\ClientContact;
use App\Services\Payroll\Evertime\Concerns\BuildsClientPayloads;
use App\Services\Payroll\Evertime\EvertimeClient;

/**
 * POST /clients/contacts — additive; updates an existing contact, adds a
 * new one, or changes the default contact, without touching any other
 * contact Evertime already has for the client (unlike CreateClient's
 * destructive /clients POST).
 */
class UpsertClientContact
{
    use BuildsClientPayloads;

    public function __construct(private readonly EvertimeClient $client) {}

    public function handle(Client $client, string $clientId, string $contactId, ?ClientContact $contact, bool $default): void
    {
        $this->client->post('/clients/contacts', [
            'ClientId' => $clientId,
            'Contacts' => [$this->contactPayload($client, $contact, $contactId, $default)],
        ]);
    }
}
