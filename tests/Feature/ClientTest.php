<?php

use App\Filament\Resources\EducationClients\Pages\EditEducationClient;
use App\Filament\Resources\EducationClients\Pages\ListEducationClients;
use App\Models\ClientContact;
use App\Models\EducationClient;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);
});

test('creating a client requires a name', function () {
    Livewire::test(ListEducationClients::class)
        ->callAction('create', data: ['name' => ''])
        ->assertHasActionErrors(['name']);

    expect(EducationClient::where('name', '')->exists())->toBeFalse();
});

test('creating a client with just a name succeeds', function () {
    Livewire::test(ListEducationClients::class)
        ->callAction('create', data: ['name' => 'Applebough Primary School'])
        ->assertHasNoActionErrors();

    expect(EducationClient::where('name', 'Applebough Primary School')->exists())->toBeTrue();
});

test('client details can be filled in later via the edit page', function () {
    $client = EducationClient::factory()->create([
        'name' => 'Applebough Primary School',
        'company_id' => $this->user->company_id,
    ]);

    Livewire::test(EditEducationClient::class, ['record' => $client->id])
        ->fillForm([
            'client_type' => 'School',
            'address' => '123 Example Road',
            'city' => 'Halesowen',
            'postcode' => 'B63 3HY',
            'county' => 'West Midlands',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($client->fresh())
        ->client_type->toBe('School')
        ->address->toBe('123 Example Road')
        ->city->toBe('Halesowen')
        ->postcode->toBe('B63 3HY')
        ->county->toBe('West Midlands');
});

test('it can create an education client', function () {
    $client = EducationClient::factory()->create([
        'name' => 'Applebough Recruitment Ltd',
        'client_type' => 'School',
        'city' => 'Halesowen',
        'postcode' => 'B63 3HY',
        'county' => 'West Midlands',
    ]);

    expect($client->name)->toBe('Applebough Recruitment Ltd')
        ->and($client->client_type)->toBe('School')
        ->and($client->city)->toBe('Halesowen')
        ->and($client->postcode)->toBe('B63 3HY')
        ->and($client->county)->toBe('West Midlands');
});

test('it has soft deletes', function () {
    $client = EducationClient::factory()->create();

    $client->delete();

    expect($client->fresh()->deleted_at)->not->toBeNull();
});

test('a contact can be added via the Contacts tab on the edit page', function () {
    $client = EducationClient::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditEducationClient::class, ['record' => $client->id])
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

    $contact = ClientContact::where('education_client_id', $client->id)->first();

    expect($contact)->not->toBeNull()
        ->and($contact->first_name)->toBe('Ashley')
        ->and($contact->last_name)->toBe('Smith')
        ->and($contact->main_contact)->toBeTrue();
});

test('setting a contact as main unsets the previous main contact', function () {
    $client = EducationClient::factory()->create(['company_id' => $this->user->company_id]);

    $firstContact = ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $client->id,
        'main_contact' => true,
    ]);

    $secondContact = ClientContact::factory()->create([
        'company_id' => $this->user->company_id,
        'education_client_id' => $client->id,
        'main_contact' => true,
    ]);

    expect($firstContact->fresh()->main_contact)->toBeFalse()
        ->and($secondContact->fresh()->main_contact)->toBeTrue()
        ->and($client->mainContact()->first()->id)->toBe($secondContact->id);
});
