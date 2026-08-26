<?php

use App\Enums\Integration;
use App\Filament\Pages\Integrations\EvertimeIntegration;
use App\Filament\Pages\Integrations\ListIntegrations;
use App\Filament\Widgets\PaymentProvidersOverview;
use App\Models\PaymentProvider;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $this->consultant->assignRole('consultant');
});

test('an admin can access the integrations list page', function () {
    $this->actingAs($this->admin);

    expect(ListIntegrations::canAccess())->toBeTrue();

    Livewire::test(ListIntegrations::class)->assertSuccessful();
});

test('a consultant cannot access the integrations list page', function () {
    $this->actingAs($this->consultant);

    expect(ListIntegrations::canAccess())->toBeFalse();
});

test('a consultant cannot access the evertime integration page', function () {
    $this->actingAs($this->consultant);

    expect(EvertimeIntegration::canAccess())->toBeFalse();
});

test('a site admin cannot access the integrations pages (a company-less account has no integrations to configure)', function () {
    $siteAdmin = User::factory()->create(['company_id' => null]);
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    expect(ListIntegrations::canAccess())->toBeFalse()
        ->and(EvertimeIntegration::canAccess())->toBeFalse();
});

test('saving the evertime connection form persists it to the company and enables the provider', function () {
    $this->actingAs($this->admin);

    Livewire::test(EvertimeIntegration::class)
        ->fillForm([
            'enabled' => true,
            'api_url' => 'https://api-staging.evertime.co.uk',
            'api_key' => 'secret-key',
        ])
        ->callAction('save')
        ->assertHasNoActionErrors();

    $company = $this->admin->company->fresh();

    expect($company->payroll_provider)->toBe(Integration::Evertime)
        ->and($company->integrationSetting(Integration::Evertime, 'api_url'))->toBe('https://api-staging.evertime.co.uk')
        ->and($company->integrationSetting(Integration::Evertime, 'api_key'))->toBe('secret-key');
});

test('turning the enabled toggle off clears payroll_provider but keeps the stored credentials', function () {
    $this->admin->company->update(['payroll_provider' => Integration::Evertime->value]);
    $this->admin->company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api.evertime.co.uk');
    $this->admin->company->setIntegrationSetting(Integration::Evertime, 'api_key', 'original-key');

    $this->actingAs($this->admin);

    Livewire::test(EvertimeIntegration::class)
        ->fillForm(['enabled' => false])
        ->callAction('save')
        ->assertHasNoActionErrors();

    $company = $this->admin->company->fresh();

    expect($company->payroll_provider)->toBeNull()
        ->and($company->payrollProvider())->toBeNull()
        ->and($company->integrationSetting(Integration::Evertime, 'api_url'))->toBe('https://api.evertime.co.uk')
        ->and($company->integrationSetting(Integration::Evertime, 'api_key'))->toBe('original-key');
});

test('re-enabling the toggle reactivates the provider without needing the api key re-entered', function () {
    $this->admin->company->update(['payroll_provider' => null]);
    $this->admin->company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api.evertime.co.uk');
    $this->admin->company->setIntegrationSetting(Integration::Evertime, 'api_key', 'original-key');

    $this->actingAs($this->admin);

    Livewire::test(EvertimeIntegration::class)
        ->fillForm(['enabled' => true])
        ->callAction('save')
        ->assertHasNoActionErrors();

    $company = $this->admin->company->fresh();

    expect($company->payroll_provider)->toBe(Integration::Evertime)
        ->and($company->integrationSetting(Integration::Evertime, 'api_key'))->toBe('original-key');
});

test('the form loads with the toggle reflecting whether evertime is the currently active provider', function () {
    $this->admin->company->update(['payroll_provider' => Integration::Evertime->value]);

    $this->actingAs($this->admin);

    Livewire::test(EvertimeIntegration::class)
        ->assertFormSet(['enabled' => true]);

    $this->admin->company->update(['payroll_provider' => null]);

    Livewire::test(EvertimeIntegration::class)
        ->assertFormSet(['enabled' => false]);
});

test('saving the evertime connection form without a new api key keeps the existing one', function () {
    $this->admin->company->update(['payroll_provider' => Integration::Evertime->value]);
    $this->admin->company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api.evertime.co.uk');
    $this->admin->company->setIntegrationSetting(Integration::Evertime, 'api_key', 'original-key');

    $this->actingAs($this->admin);

    Livewire::test(EvertimeIntegration::class)
        ->fillForm(['api_url' => 'https://api.evertime.co.uk'])
        ->callAction('save');

    expect($this->admin->company->fresh()->integrationSetting(Integration::Evertime, 'api_key'))->toBe('original-key');
});

test('a payment provider can be created from the overview widget, including its external id', function () {
    $this->actingAs($this->admin);

    Livewire::test(PaymentProvidersOverview::class)
        ->callAction(TestAction::make('create')->table(), data: [
            'name' => 'Orbital',
            'address_1' => '1 Orbital Way',
            'address_2' => 'Suite 2',
            'county' => 'Greater London',
            'postcode' => 'EC1A 1AA',
            'payroll_provider_id' => 'EVERTIME-EXISTING-42',
        ]);

    $paymentProvider = PaymentProvider::where('name', 'Orbital')->first();

    expect($paymentProvider)->not->toBeNull()
        ->and($paymentProvider->company_id)->toBe($this->admin->company_id)
        ->and($paymentProvider->postcode)->toBe('EC1A 1AA')
        ->and($paymentProvider->providerExternalId(Integration::Evertime))->toBe('EVERTIME-EXISTING-42');
});

test('a payment provider can be edited from the overview widget', function () {
    $this->actingAs($this->admin);

    $paymentProvider = PaymentProvider::factory()->create([
        'company_id' => $this->admin->company_id,
        'name' => 'Old Name',
    ]);
    $paymentProvider->setProviderExternalId(Integration::Evertime, 'OLD-ID');

    Livewire::test(PaymentProvidersOverview::class)
        ->callTableAction('edit', $paymentProvider, data: [
            'name' => 'New Name',
            'address_1' => $paymentProvider->address_1,
            'address_2' => $paymentProvider->address_2,
            'county' => $paymentProvider->county,
            'postcode' => $paymentProvider->postcode,
            'payroll_provider_id' => 'NEW-ID',
        ]);

    expect($paymentProvider->refresh()->name)->toBe('New Name')
        ->and($paymentProvider->providerExternalId(Integration::Evertime))->toBe('NEW-ID');
});

test('the overview widget only shows payment providers for the current company', function () {
    $this->actingAs($this->admin);

    $own = PaymentProvider::factory()->create(['company_id' => $this->admin->company_id]);
    $other = PaymentProvider::factory()->create();

    Livewire::test(PaymentProvidersOverview::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$other]);
});
