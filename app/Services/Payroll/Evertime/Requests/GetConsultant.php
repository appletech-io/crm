<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Services\Payroll\Evertime\EvertimeClient;

/**
 * Looks up an existing consultant by their Evertime Display Id — mainly
 * useful for verifying a manually-entered "Payroll Provider ID" actually
 * resolves to a real consultant before relying on it.
 *
 * GET /consultants
 */
class GetConsultant
{
    public function __construct(private readonly EvertimeClient $client) {}

    /** @return array<string, mixed>|null */
    public function handle(string $consultantId): ?array
    {
        $response = $this->client->get('/consultants', ['id' => $consultantId]);

        if ($response->failed() || $response->json('HasErrors')) {
            return null;
        }

        return $response->json();
    }
}
