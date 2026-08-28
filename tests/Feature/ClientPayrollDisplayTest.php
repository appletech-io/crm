<?php

use App\Enums\Integration;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create(['payroll_provider' => Integration::Evertime->value]);
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');
    $this->admin->industries()->attach($this->industry);
    $this->actingAs($this->admin);

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);
});

test('the client edit page shows the payroll provider id, read-only', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $client->setProviderExternalId(Integration::Evertime, 'EVERTIME-CLIENT-1');

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertFormFieldIsHidden('payroll_provider_id')
        ->assertSee('EVERTIME-CLIENT-1');
});

test('the client edit page shows a placeholder when there is no synced id yet', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertSee('Not yet synced');
});

test('the payroll provider id still shows even when the company has no active payroll provider configured', function () {
    $this->company->update(['payroll_provider' => null]);

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $client->setProviderExternalId(Integration::Evertime, 'EVERTIME-PRE-SYNCED');

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertSee('EVERTIME-PRE-SYNCED');
});
