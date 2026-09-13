<?php

use App\Enums\Integration;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api.evertime.test');
    $this->company->setIntegrationSetting(Integration::Evertime, 'api_key', 'test-key');

    // #15 and #19 are in the command's hardcoded CLIENT_IDS list.
    $this->houlton = Client::factory()->create([
        'id' => 15,
        'company_id' => $this->company->id,
    ]);
    $this->houlton->setProviderExternalId(Integration::Evertime, 'HOUL001');

    $this->contact = ClientContact::factory()->create([
        'client_id' => $this->houlton->id,
        'company_id' => $this->company->id,
        'email' => 'reevesb@houlton.test',
    ]);
    $this->contact->setProviderExternalId(Integration::Evertime, 'CONTACT-509');
});

test('a dry run reports the resolved contact id without writing it', function () {
    Http::fake(['*/clients/contacts*' => Http::response([
        'HasErrors' => false,
        'ClientContacts' => [
            ['ClientId' => 'HOUL001', 'ClientContactId' => '19490', 'Forename' => 'Becky', 'Surname' => 'Reeves', 'Email' => 'reevesb@houlton.test'],
        ],
    ])]);

    $this->artisan('evertime:reconcile-client-contacts')
        ->assertSuccessful();

    expect($this->contact->fresh()->providerExternalId(Integration::Evertime))->toBe('CONTACT-509');
});

test('--commit updates the local contact to the email-matched Evertime contact id', function () {
    Http::fake(['*/clients/contacts*' => Http::response([
        'HasErrors' => false,
        'ClientContacts' => [
            ['ClientId' => 'HOUL001', 'ClientContactId' => '19490', 'Forename' => 'Becky', 'Surname' => 'Reeves', 'Email' => 'reevesb@houlton.test'],
        ],
    ])]);

    $this->artisan('evertime:reconcile-client-contacts', ['--commit' => true])
        ->assertSuccessful();

    expect($this->contact->fresh()->providerExternalId(Integration::Evertime))->toBe('19490');
});

test('a contact with no matching Evertime email is left untouched and reported', function () {
    Http::fake(['*/clients/contacts*' => Http::response([
        'HasErrors' => false,
        'ClientContacts' => [
            ['ClientId' => 'HOUL001', 'ClientContactId' => '19490', 'Forename' => 'Someone', 'Surname' => 'Else', 'Email' => 'someone-else@houlton.test'],
        ],
    ])]);

    $this->artisan('evertime:reconcile-client-contacts', ['--commit' => true])
        ->assertSuccessful();

    expect($this->contact->fresh()->providerExternalId(Integration::Evertime))->toBe('CONTACT-509');
});

test('an existing booking with a stored placement id is reported but not modified', function () {
    Http::fake(['*/clients/contacts*' => Http::response(['HasErrors' => false, 'ClientContacts' => []])]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->houlton->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
    ]);
    $booking->setProviderExternalId(Integration::Evertime, 'BOOKING-17');

    $this->artisan('evertime:reconcile-client-contacts')
        ->assertSuccessful();

    expect($booking->fresh()->providerExternalId(Integration::Evertime))->toBe('BOOKING-17');
});
