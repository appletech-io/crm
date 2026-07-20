<?php

use App\Filament\Pages\ClientSettings;
use App\Filament\Resources\ClientTypes\Pages\CreateClientType;
use App\Filament\Resources\ClientTypes\Pages\EditClientType;
use App\Filament\Resources\ClientTypes\Pages\ListClientTypes;
use App\Models\ClientType;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create();
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('list page renders', function () {
    Livewire::test(ListClientTypes::class)
        ->assertSuccessful();
});

test('non-admin cannot access client types resource', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    $this->get('/crm/client-types')->assertRedirect('/crm');
});

test('site_admin can access client types resource', function () {
    $siteAdmin = User::factory()->create(['company_id' => $this->company->id]);
    $siteAdmin->industries()->attach($this->industry);
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    Cache::put("user.{$siteAdmin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$siteAdmin->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListClientTypes::class)->assertSuccessful();
});

test('can create a client type', function () {
    Livewire::test(CreateClientType::class)
        ->fillForm(['name' => 'School'])
        ->call('create')
        ->assertHasNoFormErrors();

    $clientType = ClientType::where('name', 'School')->first();

    expect($clientType)->not->toBeNull()
        ->and($clientType->company_id)->toBe($this->company->id)
        ->and($clientType->industry_id)->toBe($this->industry->id);
});

test('edit page renders', function () {
    $clientType = ClientType::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditClientType::class, ['record' => $clientType->getRouteKey()])
        ->assertSuccessful();
});

test('client type name can be updated', function () {
    $clientType = ClientType::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Old Type',
    ]);

    Livewire::test(EditClientType::class, ['record' => $clientType->getRouteKey()])
        ->fillForm(['name' => 'New Type'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($clientType->refresh()->name)->toBe('New Type');
});

test('client types are scoped to the current company and industry', function () {
    $otherCompany = Company::factory()->create();
    ClientType::factory()->create([
        'company_id' => $otherCompany->id,
        'industry_id' => $this->industry->id,
        'name' => 'Other Company Type',
    ]);

    ClientType::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'My Type',
    ]);

    Livewire::test(ListClientTypes::class)
        ->assertCanSeeTableRecords(ClientType::where('name', 'My Type')->get())
        ->assertCanNotSeeTableRecords(ClientType::where('name', 'Other Company Type')->get());
});

test('client settings page shows a client types stat linking to the resource', function () {
    ClientType::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ClientSettings::class)
        ->assertSuccessful()
        ->assertSee('Client Types');
});
