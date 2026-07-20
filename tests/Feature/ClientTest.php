<?php

use App\Enums\TimesheetFrequency;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientType;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);
});

test('creating a client requires a name', function () {
    Livewire::test(ListClients::class)
        ->callAction('create', data: ['name' => ''])
        ->assertHasActionErrors(['name']);

    expect(Client::where('name', '')->exists())->toBeFalse();
});

test('creating a client with just a name succeeds', function () {
    Livewire::test(ListClients::class)
        ->callAction('create', data: ['name' => 'Applebough Primary School'])
        ->assertHasNoActionErrors();

    expect(Client::where('name', 'Applebough Primary School')->exists())->toBeTrue();
});

test('client details can be filled in later via the edit page', function () {
    $client = Client::factory()->create([
        'name' => 'Applebough Primary School',
        'company_id' => $this->user->company_id,
    ]);

    $clientType = ClientType::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => Cache::get("user.{$this->user->id}.active_industry_id"),
        'name' => 'School',
    ]);

    Livewire::test(EditClient::class, ['record' => $client->id])
        ->fillForm([
            'client_type_id' => $clientType->id,
            'address' => '123 Example Road',
            'city' => 'Halesowen',
            'postcode' => 'B63 3HY',
            'county' => 'West Midlands',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($client->fresh())
        ->client_type_id->toBe($clientType->id)
        ->address->toBe('123 Example Road')
        ->city->toBe('Halesowen')
        ->postcode->toBe('B63 3HY')
        ->county->toBe('West Midlands');
});

test('a consultant can be assigned to a client via the edit page', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');
    $consultant->industries()->attach(Industry::where('slug', 'education')->sole());

    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditClient::class, ['record' => $client->id])
        ->fillForm(['consultant_id' => $consultant->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($client->fresh()->consultant_id)->toBe($consultant->id)
        ->and($client->fresh()->consultant->id)->toBe($consultant->id);
});

test('the consultant filter on the clients list is only visible to admins', function () {
    $this->user->assignRole('admin');

    Livewire::test(ListClients::class)
        ->assertTableFilterVisible('consultant_id');

    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", Industry::where('slug', 'education')->value('id'));

    Livewire::test(ListClients::class)
        ->assertTableFilterHidden('consultant_id');
});

test('the clients list can be filtered by consultant', function () {
    $this->user->assignRole('admin');

    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $matchingClient = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $consultant->id,
    ]);

    $otherClient = Client::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(ListClients::class)
        ->filterTable('consultant_id', $consultant->id)
        ->assertCanSeeTableRecords([$matchingClient])
        ->assertCanNotSeeTableRecords([$otherClient]);
});

test('it can create an education client', function () {
    $clientType = ClientType::factory()->create(['company_id' => $this->user->company_id, 'name' => 'School']);

    $client = Client::factory()->create([
        'name' => 'Applebough Recruitment Ltd',
        'client_type_id' => $clientType->id,
        'city' => 'Halesowen',
        'postcode' => 'B63 3HY',
        'county' => 'West Midlands',
    ]);

    expect($client->name)->toBe('Applebough Recruitment Ltd')
        ->and($client->clientType->name)->toBe('School')
        ->and($client->city)->toBe('Halesowen')
        ->and($client->postcode)->toBe('B63 3HY')
        ->and($client->county)->toBe('West Midlands');
});

test('it has soft deletes', function () {
    $client = Client::factory()->create();

    $client->delete();

    expect($client->fresh()->deleted_at)->not->toBeNull();
});

test('a client reads its timesheet frequency and day of month through from its company', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    expect($client->timesheet_frequency)->toBe(TimesheetFrequency::Weekly)
        ->and($client->timesheet_day_of_month)->toBeNull();

    $client->company->update([
        'timesheet_frequency' => TimesheetFrequency::Monthly,
        'timesheet_day_of_month' => 15,
    ]);

    $fresh = $client->fresh();

    expect($fresh->timesheet_frequency)->toBe(TimesheetFrequency::Monthly)
        ->and($fresh->timesheet_day_of_month)->toBe(15);
});

test('a contact can be added via the Contacts tab on the edit page', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditClient::class, ['record' => $client->id])
        ->fillForm([
            'contacts' => [
                'contact-1' => [
                    'first_name' => 'Ashley',
                    'last_name' => 'Smith',
                    'email' => 'ashley@example.com',
                    'main_contact' => true,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contact = ClientContact::where('client_id', $client->id)->first();

    expect($contact)->not->toBeNull()
        ->and($contact->first_name)->toBe('Ashley')
        ->and($contact->last_name)->toBe('Smith')
        ->and($contact->main_contact)->toBeTrue();
});

test('setting a contact as main unsets the previous main contact', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    $firstContact = ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'main_contact' => true,
    ]);

    $secondContact = ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'main_contact' => true,
    ]);

    expect($firstContact->fresh()->main_contact)->toBeFalse()
        ->and($secondContact->fresh()->main_contact)->toBeTrue()
        ->and($client->mainContact()->first()->id)->toBe($secondContact->id);
});
