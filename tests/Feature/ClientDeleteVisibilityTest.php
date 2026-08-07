<?php

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function actingAsClientIndustryUser(string $role): array
{
    $user = User::factory()->create();
    $user->assignRole($role);

    $industry = Industry::factory()->create();
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    test()->actingAs($user);

    return [$user, $industry];
}

test('consultants cannot see the delete action on a client', function () {
    [$user, $industry] = actingAsClientIndustryUser('consultant');
    $client = Client::factory()->create(['company_id' => $user->company_id, 'industry_id' => $industry->id, 'consultant_id' => $user->id]);

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertActionHidden('delete');
});

test('admins can see the delete action on a client', function () {
    [$user, $industry] = actingAsClientIndustryUser('admin');
    $client = Client::factory()->create(['company_id' => $user->company_id, 'industry_id' => $industry->id, 'consultant_id' => $user->id]);

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertActionVisible('delete');
});

test('consultants cannot see the delete bulk action on the clients table', function () {
    actingAsClientIndustryUser('consultant');

    Livewire::test(ListClients::class)
        ->assertTableBulkActionHidden('delete');
});

test('admins can see the delete bulk action on the clients table', function () {
    actingAsClientIndustryUser('admin');

    Livewire::test(ListClients::class)
        ->assertTableBulkActionVisible('delete');
});

test('consultants cannot see the force delete action on a trashed client', function () {
    [$user, $industry] = actingAsClientIndustryUser('consultant');
    $client = Client::factory()->create(['company_id' => $user->company_id, 'industry_id' => $industry->id, 'consultant_id' => $user->id]);
    $client->delete();

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertActionHidden('forceDelete');
});

test('admins can see the force delete action on a trashed client', function () {
    [$user, $industry] = actingAsClientIndustryUser('admin');
    $client = Client::factory()->create(['company_id' => $user->company_id, 'industry_id' => $industry->id, 'consultant_id' => $user->id]);
    $client->delete();

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertActionVisible('forceDelete');
});

test('consultants cannot see the force delete bulk action on the clients table', function () {
    actingAsClientIndustryUser('consultant');

    Livewire::test(ListClients::class)
        ->filterTable('trashed', true)
        ->assertTableBulkActionHidden('forceDelete');
});

test('admins can see the force delete bulk action on the clients table', function () {
    actingAsClientIndustryUser('admin');

    Livewire::test(ListClients::class)
        ->filterTable('trashed', true)
        ->assertTableBulkActionVisible('forceDelete');
});
