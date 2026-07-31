<?php

use App\Filament\Pages\ClientSettings;
use App\Filament\Resources\ClientPools\ClientPoolResource;
use App\Filament\Resources\ClientPools\Pages\EditClientPool;
use App\Filament\Resources\ClientPools\Pages\ListClientPools;
use App\Models\ClientPool;
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

    Cache::flush();
});

function actingAsClientPoolUser(string $role): User
{
    $user = User::factory()->create(['company_id' => test()->company->id]);
    $user->industries()->attach(test()->industry);
    $user->assignRole($role);
    test()->actingAs($user);

    Cache::put("user.{$user->id}.active_industry", test()->industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", test()->industry->id);

    return $user;
}

test('list page renders', function () {
    actingAsClientPoolUser('consultant');

    Livewire::test(ListClientPools::class)->assertSuccessful();
});

test('a consultant creating a pool always gets a personal pool, with no company-pool toggle available', function () {
    $consultant = actingAsClientPoolUser('consultant');

    Livewire::test(ListClientPools::class)
        ->mountAction('create')
        ->assertMountedActionModalDontSee('Company Pool')
        ->setActionData(['name' => 'My Pool'])
        ->callMountedAction();

    $pool = ClientPool::where('name', 'My Pool')->first();

    expect($pool)->not->toBeNull()
        ->and($pool->user_id)->toBe($consultant->id)
        ->and($pool->company_pool)->toBeFalse()
        ->and($pool->industry_id)->toBe($this->industry->id);
});

test('an admin can create a company-wide pool visible to everyone', function () {
    actingAsClientPoolUser('admin');

    Livewire::test(ListClientPools::class)
        ->mountAction('create')
        ->assertMountedActionModalSee('Company Pool')
        ->setActionData(['name' => 'Company Pool', 'company_pool' => true])
        ->callMountedAction();

    $pool = ClientPool::where('name', 'Company Pool')->first();

    expect($pool)->not->toBeNull()
        ->and($pool->user_id)->toBeNull()
        ->and($pool->company_pool)->toBeTrue();
});

test('edit page renders', function () {
    $user = actingAsClientPoolUser('consultant');
    $pool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $user->id,
    ]);

    Livewire::test(EditClientPool::class, ['record' => $pool->getRouteKey()])
        ->assertSuccessful();
});

test('a user sees their own pools and company pools, but not another users personal pool', function () {
    $me = actingAsClientPoolUser('consultant');
    $someoneElse = User::factory()->create(['company_id' => $this->company->id]);
    $someoneElse->industries()->attach($this->industry);

    $mine = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $me->id,
        'name' => 'Mine',
    ]);
    $companyWide = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => null,
        'company_pool' => true,
        'name' => 'Shared',
    ]);
    $someoneElsesPersonalPool = ClientPool::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $someoneElse->id,
        'name' => 'Not Mine',
    ]);

    Livewire::test(ListClientPools::class)
        ->assertCanSeeTableRecords([$mine, $companyWide])
        ->assertCanNotSeeTableRecords([$someoneElsesPersonalPool]);
});

test('client pools resource is hidden from the main navigation', function () {
    expect(ClientPoolResource::shouldRegisterNavigation())->toBeFalse();
});

test('client settings page shows a client pools stat linking to the resource', function () {
    $user = actingAsClientPoolUser('consultant');
    ClientPool::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'user_id' => $user->id,
    ]);

    Livewire::test(ClientSettings::class)
        ->assertSuccessful()
        ->assertSee('Client Pools');
});
