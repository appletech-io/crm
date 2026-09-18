<?php

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientLocation;
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

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);
});

test('a location can be added via the Locations tab on the client edit page', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => Cache::get("user.{$this->user->id}.active_industry_id"),
    ]);

    Livewire::test(EditClient::class, ['record' => $client->id])
        ->fillForm([
            'locations' => [
                'location-1' => [
                    'name' => 'Sixth Form Building',
                    'address' => '1 Example Road',
                    'city' => 'Birmingham',
                    'county' => 'West Midlands',
                    'postcode' => 'B1 1AA',
                    'phone' => '0121 111 2222',
                    'is_default' => true,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $location = ClientLocation::where('client_id', $client->id)->first();

    expect($location)->not->toBeNull()
        ->and($location->name)->toBe('Sixth Form Building')
        ->and($location->postcode)->toBe('B1 1AA')
        ->and($location->is_default)->toBeTrue();
});

test('setting a location as default unsets the previous default location', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    $firstLocation = ClientLocation::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'is_default' => true,
    ]);

    $secondLocation = ClientLocation::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'is_default' => true,
    ]);

    expect($firstLocation->fresh()->is_default)->toBeFalse()
        ->and($secondLocation->fresh()->is_default)->toBeTrue()
        ->and($client->defaultLocation()->first()->id)->toBe($secondLocation->id);
});

test('a location belongs to only one client, scoped correctly via the relation', function () {
    $clientA = Client::factory()->create(['company_id' => $this->user->company_id]);
    $clientB = Client::factory()->create(['company_id' => $this->user->company_id]);

    $locationA = ClientLocation::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $clientA->id]);
    ClientLocation::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $clientB->id]);

    expect($clientA->locations()->pluck('id')->all())->toBe([$locationA->id]);
});

test('an already-saved location can be assigned to a contact via the Contacts tab', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => Cache::get("user.{$this->user->id}.active_industry_id"),
    ]);
    $location = ClientLocation::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'name' => 'Nursery Building',
    ]);
    $contact = ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
    ]);

    Livewire::test(EditClient::class, ['record' => $client->id])
        ->fillForm([
            'contacts' => [
                "record-{$contact->id}" => [
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'location_id' => $location->id,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contact->fresh()->location_id)->toBe($location->id);
});

test('adding a brand new, not-yet-saved location alongside a contact does not error and leaves the contact unassigned', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => Cache::get("user.{$this->user->id}.active_industry_id"),
    ]);
    $contact = ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
    ]);

    // The new location has no 'id' yet within this request — the Location
    // select's options() closure must tolerate that (filter it out) rather
    // than erroring on a repeater entry with no 'id' key.
    Livewire::test(EditClient::class, ['record' => $client->id])
        ->fillForm([
            'locations' => [
                'location-1' => ['name' => 'New Unsaved Location'],
            ],
            'contacts' => [
                "record-{$contact->id}" => [
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contact->fresh()->location_id)->toBeNull();
});
