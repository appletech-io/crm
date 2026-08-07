<?php

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('a non-admin consultant only sees clients assigned to them', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $ownClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $consultant->id,
    ]);

    $otherConsultantClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->user->id,
    ]);

    $unassignedClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListClients::class)
        ->assertCanSeeTableRecords([$ownClient])
        ->assertCanNotSeeTableRecords([$otherConsultantClient, $unassignedClient]);
});

test('an admin only sees their own clients by default, same as a consultant', function () {
    $otherConsultant = User::factory()->create(['company_id' => $this->user->company_id]);

    $ownClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->user->id,
    ]);

    $otherClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $otherConsultant->id,
    ]);

    Livewire::test(ListClients::class)
        ->assertCanSeeTableRecords([$ownClient])
        ->assertCanNotSeeTableRecords([$otherClient]);
});

test('an admin can toggle to see all clients, and toggle back to just their own', function () {
    $otherConsultant = User::factory()->create(['company_id' => $this->user->company_id]);

    $ownClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->user->id,
    ]);

    $otherClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $otherConsultant->id,
    ]);

    $test = Livewire::test(ListClients::class)
        ->assertActionHasLabel('toggleAllClients', 'Show All Clients')
        ->callAction('toggleAllClients')
        ->assertCanSeeTableRecords([$ownClient, $otherClient])
        ->assertActionHasLabel('toggleAllClients', 'Show My Clients Only');

    $test->callAction('toggleAllClients')
        ->assertCanSeeTableRecords([$ownClient])
        ->assertCanNotSeeTableRecords([$otherClient])
        ->assertActionHasLabel('toggleAllClients', 'Show All Clients');
});

test('consultants do not see the show all clients toggle', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListClients::class)
        ->assertActionHidden('toggleAllClients');
});

test('a non-admin cannot directly open another consultants client edit page', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $otherConsultantClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->user->id,
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    expect(fn () => Livewire::test(EditClient::class, ['record' => $otherConsultantClient->getRouteKey()]))
        ->toThrow(ModelNotFoundException::class);
});

test('an admin cannot directly open another consultants client edit page unless viewing all clients', function () {
    $otherConsultant = User::factory()->create(['company_id' => $this->user->company_id]);

    $otherConsultantClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $otherConsultant->id,
    ]);

    expect(fn () => Livewire::test(EditClient::class, ['record' => $otherConsultantClient->getRouteKey()]))
        ->toThrow(ModelNotFoundException::class);

    session([Client::ADMIN_VIEWING_ALL_CLIENTS_SESSION_KEY => true]);

    Livewire::test(EditClient::class, ['record' => $otherConsultantClient->getRouteKey()])
        ->assertSuccessful();
});

test('creating a client assigns the logged in consultant', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListClients::class)
        ->callAction('create', data: ['name' => 'New School'])
        ->assertHasNoActionErrors();

    $client = Client::where('name', 'New School')->first();

    expect($client)->not->toBeNull()
        ->and($client->consultant_id)->toBe($consultant->id);
});
