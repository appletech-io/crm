<?php

namespace App\Services\Payroll\Evertime\Concerns;

use App\Models\Client;
use App\Models\ClientContact;

/**
 * Contact/location/financials payload shapes shared between CreateClient
 * (the full /clients POST used for a brand-new client) and the standalone
 * UpsertClientContact/UpsertClientLocation/UpdateClient calls used to keep
 * an already-known client current — kept in one place so the two paths
 * can never silently drift apart on what a "contact" or "location" looks
 * like to Evertime.
 */
trait BuildsClientPayloads
{
    /** @return array<string, mixed> */
    private function contactPayload(Client $client, ?ClientContact $contact, string $contactId, bool $default): array
    {
        return [
            'ContactId' => $contactId,
            'DefaultContact' => $default,
            // Not defaulted to true by Evertime when omitted — without it,
            // timesheet/placement approver lookups reject this contact
            // ("An active client contact was not found...").
            'Active' => true,
            'Forename' => $contact?->first_name ?? $client->name,
            'Surname' => $contact?->last_name ?? 'Contact',
        ];
    }

    /** @return array<string, mixed> */
    private function locationPayload(Client $client, string $locationId): array
    {
        return [
            'LocationId' => $locationId,
            'DefaultLocation' => true,
            'Name' => $client->name,
            'AddressLine1' => $client->address,
            'PostCode' => $client->postcode,
        ];
    }

    /** @return array<string, mixed> */
    private function financialsPayload(): array
    {
        // No VAT concept exists on Client yet — Standard (20%) is the
        // common default for UK clients. Unlike Candidate/Company VatCode
        // (which takes the short code, e.g. "Standard"), Client.Financials.
        // VatCode requires the full description string — confirmed against
        // a real 422 ("The supplied VatCode of 'Standard' is invalid") when
        // the short code was sent here.
        return ['VatCode' => 'Standard 20%'];
    }
}
