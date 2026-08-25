<?php

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\ClientType;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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

test('the quick view action is visible for a client row and mounts a fully populated summary without error', function () {
    $clientType = ClientType::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Primary School']);

    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->user->id,
        'client_type_id' => $clientType->id,
        'key_stages' => ['keystage_1', 'keystage_2'],
    ]);

    Livewire::test(ListClients::class)
        ->assertTableActionVisible('viewClientSummary', $client)
        ->mountTableAction('viewClientSummary', $client)
        ->assertSuccessful();
});

test('the quick view action mounts a summary with every optional field blank without error', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'client_type_id' => null,
        'consultant_id' => null,
        'website' => null,
        'notes' => null,
        'key_stages' => null,
        'address' => null,
        'city' => null,
        'postcode' => null,
        'county' => null,
    ]);

    Livewire::test(ListClients::class)
        ->mountTableAction('viewClientSummary', $client)
        ->assertSuccessful();
});
