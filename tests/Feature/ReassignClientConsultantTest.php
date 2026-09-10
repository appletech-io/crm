<?php

use App\Filament\Resources\Clients\Pages\ListClients;
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

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);

    session(['admin_viewing_all_clients' => true]);
});

test('an admin can bulk-reassign clients to a new consultant', function () {
    $oldConsultant = User::factory()->create(['company_id' => $this->company->id]);
    $oldConsultant->assignRole('consultant');
    $oldConsultant->industries()->attach($this->industry);

    $newConsultant = User::factory()->create(['company_id' => $this->company->id]);
    $newConsultant->assignRole('consultant');
    $newConsultant->industries()->attach($this->industry);

    $clients = Client::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $oldConsultant->id,
    ]);

    Livewire::test(ListClients::class)
        ->selectTableRecords($clients->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->callAction(TestAction::make('reassignConsultant')->table()->bulk(), data: ['consultant_id' => $newConsultant->id])
        ->assertHasNoActionErrors();

    $clients->each(fn (Client $client) => expect($client->fresh()->consultant_id)->toBe($newConsultant->id));

    $newPool = ClientPool::where('user_id', $newConsultant->id)->where('is_primary', true)->first();
    $oldPool = ClientPool::where('user_id', $oldConsultant->id)->where('is_primary', true)->first();

    expect($newPool->clients()->count())->toBe(2)
        ->and($oldPool->clients()->count())->toBe(0);
});

test('a non-admin cannot see the reassign consultant bulk action', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->assignRole('consultant');
    $consultant->industries()->attach($this->industry);
    $this->actingAs($consultant);

    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListClients::class)
        ->assertActionHidden(TestAction::make('reassignConsultant')->table()->bulk());
});
