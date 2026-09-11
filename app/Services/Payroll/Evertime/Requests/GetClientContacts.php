<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Services\Payroll\Evertime\EvertimeClient;

/**
 * Looks up every contact Evertime already has registered for a client —
 * mainly useful for reconciling a client whose ClientId has been repointed
 * to an already-existing Evertime customer record, whose contacts (and
 * their ClientContactIds) this app has no record of yet.
 *
 * GET /clients/contacts?id={clientId}
 */
class GetClientContacts
{
    public function __construct(private readonly EvertimeClient $client) {}

    /** @return array<int, array<string, mixed>> */
    public function handle(string $clientId): array
    {
        $response = $this->client->get('/clients/contacts', ['id' => $clientId]);

        if ($response->failed() || $response->json('HasErrors')) {
            return [];
        }

        return $response->json('ClientContacts') ?? [];
    }
}
