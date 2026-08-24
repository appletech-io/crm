<?php

use App\Enums\Integration;
use App\Models\Client;

test('resolveProviderExternalId generates and persists a fallback when none exists', function () {
    $client = Client::factory()->create();

    $id = $client->resolveProviderExternalId(Integration::Evertime, fn (): string => "CLIENT-{$client->id}");

    expect($id)->toBe("CLIENT-{$client->id}");
    expect($client->providerExternalId(Integration::Evertime))->toBe("CLIENT-{$client->id}");
});

test('resolveProviderExternalId reuses a stored value rather than regenerating', function () {
    $client = Client::factory()->create();
    $client->setProviderExternalId(Integration::Evertime, 'PRE-EXISTING-123');

    $id = $client->resolveProviderExternalId(Integration::Evertime, fn (): string => "CLIENT-{$client->id}");

    expect($id)->toBe('PRE-EXISTING-123');
});

test('setProviderExternalId overwrites an existing value', function () {
    $client = Client::factory()->create();
    $client->setProviderExternalId(Integration::Evertime, 'OLD-ID');
    $client->setProviderExternalId(Integration::Evertime, 'NEW-ID');

    expect($client->providerExternalId(Integration::Evertime))->toBe('NEW-ID');
    expect($client->providerExternalIds()->count())->toBe(1);
});

test('setProviderExternalId with a blank value clears the stored id', function () {
    $client = Client::factory()->create();
    $client->setProviderExternalId(Integration::Evertime, 'SOME-ID');

    $client->setProviderExternalId(Integration::Evertime, null);

    expect($client->providerExternalId(Integration::Evertime))->toBeNull();
    expect($client->providerExternalIds()->count())->toBe(0);
});
