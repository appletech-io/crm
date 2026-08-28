<?php

use App\Enums\Integration;
use App\Filament\Resources\CompanyUsers\Pages\EditCompanyUser;
use App\Jobs\SyncPayrollProviderRecord;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\ProviderError;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function fakeEvertimeCompanyForSync(): Company
{
    $company = Company::factory()->create(['payroll_provider' => Integration::Evertime->value]);

    $company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api-staging.evertime.co.uk');
    $company->setIntegrationSetting(Integration::Evertime, 'api_key', 'test-key');

    return $company;
}

test('saving a client dispatches a sync to the payroll provider', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompanyForSync();
    $client = Client::factory()->create(['company_id' => $company->id]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/clients')
        && $request['Name'] === $client->name);
});

test('saving an education candidate dispatches a sync to the payroll provider', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompanyForSync();
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'ni_number' => 'QQ123456C',
        'date_of_birth' => '1990-01-01',
        'gender' => 'female',
    ]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/candidates')
        && $request['Forenames'] === $candidate->first_name);
});

test('saving a healthcare candidate dispatches a sync to the payroll provider', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompanyForSync();
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $company->id,
        'ni_number' => 'QQ123456C',
        'date_of_birth' => '1990-01-01',
        'gender' => 'female',
    ]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/candidates')
        && $request['Forenames'] === $candidate->first_name);
});

test('saving a generic candidate now attempts a sync instead of being silently unsupported', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompanyForSync();
    $candidate = Candidate::factory()->create(['company_id' => $company->id]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/candidates'));
});

test('saving a consultant dispatches a sync to the payroll provider', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    (new RoleSeeder)->run();
    $company = fakeEvertimeCompanyForSync();
    $consultant = User::factory()->create(['company_id' => $company->id, 'name' => 'Jane Doe']);
    $consultant->assignRole('consultant');

    // assignRole() writes to the roles pivot table directly, not a save()
    // on the User model, so it doesn't retrigger the observer on its own —
    // mirrors the real UI flow, where a role change is followed by the
    // rest of the form saving.
    $consultant->touch();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/consultants')
        && $request['Consultants'][0]['Forenames'] === 'Jane'
        && $request['Consultants'][0]['Surname'] === 'Doe');
});

test('saving a non-consultant user does not dispatch a sync', function () {
    Http::fake();

    (new RoleSeeder)->run();
    $company = fakeEvertimeCompanyForSync();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('resourcer');
    $user->touch();

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/consultants'));
});

test('a 4xx validation error from the provider is recorded against a consultant and does not throw', function () {
    Http::fake(['*' => Http::response([
        'HasErrors' => true,
        'Errors' => [['ErrorMessage' => 'Branch not specified.']],
    ], 422)]);

    (new RoleSeeder)->run();
    $company = fakeEvertimeCompanyForSync();
    $consultant = User::factory()->create(['company_id' => $company->id]);
    $consultant->assignRole('consultant');
    $consultant->touch();

    $providerError = ProviderError::where('user_id', $consultant->id)->first();

    expect($providerError)->not->toBeNull()
        ->and($providerError->errors)->toBe(['Branch not specified.']);
});

test('nothing is dispatched when the company has no payroll provider configured', function () {
    Http::fake();

    $company = Company::factory()->create(['payroll_provider' => null]);
    Client::factory()->create(['company_id' => $company->id]);

    // Client creation may trigger unrelated geocoding HTTP calls (see
    // ClientObserver), so this checks specifically for no payroll-provider
    // traffic rather than none at all.
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/clients'));
});

test('nothing is dispatched and no error is thrown when the record has no company at all', function () {
    Http::fake();

    // A handful of test fixtures elsewhere in the suite create a candidate
    // with no company on purpose (e.g. to test document-requirement logic
    // in isolation) — saving it must not crash.
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Http::assertNothingSent();
    expect(ProviderError::count())->toBe(0);
});

test('a successful sync clears a previously recorded provider error for a client', function () {
    // No payroll provider configured yet, so creating the client below
    // doesn't trigger its own (real) dispatch before the error row exists.
    $company = Company::factory()->create(['payroll_provider' => null]);
    $client = Client::factory()->create(['company_id' => $company->id]);

    $company->update(['payroll_provider' => Integration::Evertime->value]);
    $company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api-staging.evertime.co.uk');
    $company->setIntegrationSetting(Integration::Evertime, 'api_key', 'test-key');

    ProviderError::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'provider' => Integration::Evertime->value,
        'errors' => ['Some earlier failure'],
    ]);

    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    SyncPayrollProviderRecord::dispatchSync($client->fresh());

    expect(ProviderError::where('client_id', $client->id)->exists())->toBeFalse();
});

test('a 4xx validation error from the provider is recorded against the client and does not throw', function () {
    Http::fake(['*' => Http::response([
        'HasErrors' => true,
        'Errors' => [['ErrorMessage' => "The supplied VatCode of 'Standard' is invalid."]],
    ], 422)]);

    $company = fakeEvertimeCompanyForSync();
    $client = Client::factory()->create(['company_id' => $company->id]);

    $providerError = ProviderError::where('client_id', $client->id)->first();

    expect($providerError)->not->toBeNull()
        ->and($providerError->provider)->toBe(Integration::Evertime->value)
        ->and($providerError->company_id)->toBe($company->id)
        ->and($providerError->errors)->toBe(["The supplied VatCode of 'Standard' is invalid."]);
});

test('a 4xx validation error from the provider is recorded against a candidate and does not throw', function () {
    Http::fake(['*' => Http::response([
        'HasErrors' => true,
        'Errors' => [['ErrorMessage' => 'Please supply a NINumber for candidate.']],
    ], 422)]);

    $company = fakeEvertimeCompanyForSync();
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'ni_number' => 'QQ123456C',
        'date_of_birth' => '1990-01-01',
        'gender' => 'female',
    ]);

    $providerError = ProviderError::where('candidate_type', EducationCandidate::class)
        ->where('candidate_id', $candidate->id)
        ->first();

    expect($providerError)->not->toBeNull()
        ->and($providerError->errors)->toBe(['Please supply a NINumber for candidate.']);
});

test('a server error is rethrown for the queue to retry, but still recorded', function () {
    // No payroll provider configured yet, so creating the client below
    // doesn't trigger its own (sync, real) dispatch against this same fake.
    $company = Company::factory()->create(['payroll_provider' => null]);
    $client = Client::factory()->create(['company_id' => $company->id]);

    $company->update(['payroll_provider' => Integration::Evertime->value]);
    $company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api-staging.evertime.co.uk');
    $company->setIntegrationSetting(Integration::Evertime, 'api_key', 'test-key');

    Http::fake(['*' => Http::response(['Message' => 'Internal error'], 500)]);

    $job = new SyncPayrollProviderRecord($client->fresh());

    expect(fn () => $job->handle())->toThrow(RequestException::class);

    expect(ProviderError::where('client_id', $client->id)->exists())->toBeTrue();
});

test('the retry payroll sync action on a consultants edit page clears a recorded error on success', function () {
    (new RoleSeeder)->run();

    // Provider isn't enabled until after the consultant exists, so their
    // own independent sync (fired by UserObserver on save) doesn't run
    // ahead of this test's manually-recorded error below.
    $company = Company::factory()->create(['payroll_provider' => null]);
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $consultant = User::factory()->create(['company_id' => $company->id]);
    $consultant->assignRole('consultant');

    $company->update(['payroll_provider' => Integration::Evertime->value]);
    $company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api-staging.evertime.co.uk');
    $company->setIntegrationSetting(Integration::Evertime, 'api_key', 'test-key');

    ProviderError::create([
        'company_id' => $company->id,
        'user_id' => $consultant->id,
        'provider' => Integration::Evertime->value,
        'errors' => ['Branch not specified.'],
    ]);

    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $this->actingAs($admin);

    Livewire::test(EditCompanyUser::class, ['record' => $consultant->getRouteKey()])
        ->assertActionVisible('retryPayrollSync')
        ->callAction('retryPayrollSync')
        ->assertNotified('Payroll sync retried successfully');

    expect(ProviderError::where('user_id', $consultant->id)->exists())->toBeFalse();
});

test('updating an existing client dispatches a sync with the new details, not just creating one', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompanyForSync();
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Original Name']);

    $client->update(['name' => 'Updated Name']);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/clients')
        && $request['Name'] === 'Updated Name');
});

test('updating an existing candidate dispatches a sync with the new details, not just creating one', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompanyForSync();
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'first_name' => 'Original',
        'ni_number' => 'QQ123456C',
        'date_of_birth' => '1990-01-01',
        'gender' => 'female',
    ]);

    $candidate->update(['first_name' => 'Updated']);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/candidates')
        && $request['Forenames'] === 'Updated');
});

test('updating an existing consultant dispatches a sync with the new details, not just creating one', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    (new RoleSeeder)->run();
    $company = fakeEvertimeCompanyForSync();
    $consultant = User::factory()->create(['company_id' => $company->id, 'name' => 'Original Name']);
    $consultant->assignRole('consultant');

    $consultant->update(['name' => 'Updated Name']);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/consultants')
        && $request['Consultants'][0]['Forenames'] === 'Updated'
        && $request['Consultants'][0]['Surname'] === 'Name');
});
