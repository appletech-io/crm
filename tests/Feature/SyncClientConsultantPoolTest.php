<?php

use App\Actions\Clients\SyncClientConsultantPool;
use App\Models\Client;
use App\Models\ClientPool;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create();
    $this->company->industries()->attach($this->industry);
});

test('creating a client auto-creates and attaches the consultant\'s main pool', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->assignRole('consultant');
    $consultant->industries()->attach($this->industry);
    $this->actingAs($consultant);

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $consultant->id,
    ]);

    $pool = ClientPool::where('user_id', $consultant->id)->where('is_primary', true)->first();

    expect($pool)->not->toBeNull()
        ->and($pool->industry_id)->toBe($this->industry->id)
        ->and($pool->clients()->pluck('clients.id')->all())->toBe([$client->id]);
});

test('changing consultant_id moves the client between main pools without duplicating rows', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $consultantA = User::factory()->create(['company_id' => $this->company->id]);
    $consultantA->assignRole('consultant');
    $consultantA->industries()->attach($this->industry);

    $consultantB = User::factory()->create(['company_id' => $this->company->id]);
    $consultantB->assignRole('consultant');
    $consultantB->industries()->attach($this->industry);

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $consultantA->id,
    ]);

    $poolA = ClientPool::where('user_id', $consultantA->id)->where('is_primary', true)->first();
    expect($poolA->clients()->pluck('clients.id')->all())->toBe([$client->id]);

    $client->update(['consultant_id' => $consultantB->id]);

    $poolB = ClientPool::where('user_id', $consultantB->id)->where('is_primary', true)->first();

    expect($poolA->fresh()->clients()->count())->toBe(0)
        ->and($poolB->clients()->pluck('clients.id')->all())->toBe([$client->id]);

    // Re-running the sync directly should stay idempotent, not duplicate the pivot row.
    SyncClientConsultantPool::run($client->fresh());

    expect($poolB->clients()->count())->toBe(1);
});

test('a client with no consultant is not attached to any main pool', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => null,
    ]);

    expect($client->pools()->where('is_primary', true)->count())->toBe(0);
});
