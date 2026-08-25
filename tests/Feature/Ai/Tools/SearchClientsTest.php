<?php

use App\Ai\Tools\SearchClients;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientType;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create();
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('it returns clients matching the name filter, with type and main contact', function () {
    $type = ClientType::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Primary School',
    ]);

    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
        'client_type_id' => $type->id,
    ]);

    ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'first_name' => 'Pat',
        'last_name' => 'Jones',
        'email' => 'pat@example.com',
        'main_contact' => true,
    ]);

    $result = (new SearchClients)->handle(new Request(['name' => 'Riverside']));

    expect($result)->toContain('Riverside School')
        ->and($result)->toContain('Primary School')
        ->and($result)->toContain('Pat Jones')
        ->and($result)->toContain('pat@example.com');
});

test('it filters by region matched against city, county, or postcode', function () {
    Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Leicester School',
        'city' => 'Leicester',
    ]);
    Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Manchester School',
        'city' => 'Manchester',
    ]);

    $result = (new SearchClients)->handle(new Request(['region' => 'Leicester']));

    expect($result)->toContain('Leicester School')
        ->and($result)->not->toContain('Manchester School');
});

test('it filters by client type', function () {
    $primaryType = ClientType::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Primary School',
    ]);
    $secondaryType = ClientType::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Secondary School',
    ]);

    Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
        'client_type_id' => $primaryType->id,
    ]);
    Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Oakwood School',
        'client_type_id' => $secondaryType->id,
    ]);

    $result = (new SearchClients)->handle(new Request(['type' => 'Primary']));

    expect($result)->toContain('Riverside School')
        ->and($result)->not->toContain('Oakwood School');
});

test('it links each client to their edit page', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Riverside School',
    ]);

    $result = (new SearchClients)->handle(new Request(['name' => 'Riverside']));

    $url = ClientResource::getUrl('edit', ['record' => $client]);
    expect($result)->toContain("[Riverside School]({$url})");
});

test('it returns a plain message when nothing matches', function () {
    $result = (new SearchClients)->handle(new Request(['name' => 'Nonexistent']));

    expect($result)->toBe('No clients matched.');
});

test('it paginates results and reports how many more match', function () {
    Client::factory()->count(51)->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => fn () => 'Paginated School '.Str::random(8),
    ]);

    $firstPage = (new SearchClients)->handle(new Request(['name' => 'Paginated School']));

    expect($firstPage)->toContain('Showing 50 of 51 — 1 more match. Ask to see the next 50 to continue.');

    $secondPage = (new SearchClients)->handle(new Request(['name' => 'Paginated School', 'offset' => 50]));

    expect($secondPage)->not->toContain('more match');
});

test('it does not return clients from a different industry', function () {
    $otherIndustry = Industry::factory()->create();

    Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $otherIndustry->id,
        'name' => 'Other Industry Client',
    ]);

    $result = (new SearchClients)->handle(new Request(['name' => 'Other Industry Client']));

    expect($result)->toBe('No clients matched.');
});
