<?php

use App\Filament\Resources\ClientPools\Pages\EditClientPool;
use App\Filament\Resources\ClientPools\RelationManagers\ClientsRelationManager;
use App\Models\Client;
use App\Models\ClientPool;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create();
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('consultant');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->pool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $this->user->id,
    ]);
});

test('a client can be attached to a pool', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $this->pool,
        'pageClass' => EditClientPool::class,
    ])
        ->callAction(TestAction::make('attach')->table(), data: ['recordId' => [$client->id]]);

    expect($this->pool->clients()->pluck('clients.id')->all())->toBe([$client->id]);
});

test('a client can be detached from a pool', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $this->pool->clients()->attach($client);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $this->pool,
        'pageClass' => EditClientPool::class,
    ])
        ->callAction(TestAction::make('detach')->table(record: $client));

    expect($this->pool->clients()->count())->toBe(0);
});

test('a consultant cannot add or remove clients on a company pool', function () {
    $companyPool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => null,
        'company_pool' => true,
    ]);
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);
    $companyPool->clients()->attach($client);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $companyPool,
        'pageClass' => EditClientPool::class,
    ])
        ->assertActionHidden(TestAction::make('attach')->table())
        ->assertActionHidden(TestAction::make('detach')->table(record: $client));
});

test('an admin can add and remove clients on a company pool', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->industries()->attach($this->industry);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Cache::put("user.{$admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$admin->id}.active_industry_id", $this->industry->id);

    $companyPool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => null,
        'company_pool' => true,
    ]);
    $client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => $this->industry->id]);

    Livewire::test(ClientsRelationManager::class, [
        'ownerRecord' => $companyPool,
        'pageClass' => EditClientPool::class,
    ])
        ->assertActionVisible(TestAction::make('attach')->table())
        ->callAction(TestAction::make('attach')->table(), data: ['recordId' => [$client->id]]);

    expect($companyPool->clients()->pluck('clients.id')->all())->toBe([$client->id]);
});
