<?php

use App\Enums\Integration;
use App\Models\Company;
use App\Services\Payroll\Evertime\EvertimeClient;
use App\Services\Payroll\Evertime\Requests\GetClientContacts;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api.evertime.test');
    $this->company->setIntegrationSetting(Integration::Evertime, 'api_key', 'test-key');
});

test('fetches a clients contacts using the id query parameter', function () {
    Http::fake(['*' => Http::response([
        'HasErrors' => false,
        'ClientContacts' => [
            ['ClientId' => 'HOUL001', 'ClientContactId' => '19490', 'Forename' => 'Becky', 'Surname' => 'Reeves', 'Email' => 'reevesb@houlton.test'],
        ],
    ])]);

    $contacts = (new GetClientContacts(new EvertimeClient($this->company)))->handle('HOUL001');

    expect($contacts)->toHaveCount(1)
        ->and($contacts[0]['ClientContactId'])->toBe('19490')
        ->and($contacts[0]['Email'])->toBe('reevesb@houlton.test');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.evertime.test/clients/contacts?id=HOUL001');
});

test('returns an empty array when Evertime reports an error', function () {
    Http::fake(['*' => Http::response([
        'HasErrors' => true,
        'Errors' => [['ErrorMessage' => 'No client found. Please include at least one ClientId', 'ErrorCode' => 4]],
    ], 404)]);

    $contacts = (new GetClientContacts(new EvertimeClient($this->company)))->handle('UNKNOWN');

    expect($contacts)->toBe([]);
});

test('returns an empty array when the request fails outright', function () {
    Http::fake(['*' => Http::response(null, 500)]);

    $contacts = (new GetClientContacts(new EvertimeClient($this->company)))->handle('HOUL001');

    expect($contacts)->toBe([]);
});
