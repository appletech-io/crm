<?php

use App\Actions\Clients\CreateClientContactPortalAccount;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Jobs\SendClientPortalWelcomeEmail;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Bus;
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

    $this->client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $industry->id,
    ]);
});

test('creating a contact with portal access enabled creates a linked user and sends the welcome email', function () {
    Bus::fake();

    Livewire::test(EditClient::class, ['record' => $this->client->getRouteKey()])
        ->fillForm([
            'contacts' => [
                'item-1' => [
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'email' => 'jane.doe@example.com',
                    'wants_portal_access' => true,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $contact = ClientContact::where('email', 'jane.doe@example.com')->firstOrFail();

    $user = User::where('email', 'jane.doe@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->client_contact_id)->toBe($contact->id)
        ->and($user->requires_account_setup)->toBeTrue()
        ->and($user->hasRole('client'))->toBeTrue()
        ->and($user->password)->not->toBeNull();

    Bus::assertDispatched(SendClientPortalWelcomeEmail::class, fn ($job) => $job->contact->is($contact));
});

test('creating a contact with portal access disabled does not create a user', function () {
    Bus::fake();

    Livewire::test(EditClient::class, ['record' => $this->client->getRouteKey()])
        ->fillForm([
            'contacts' => [
                'item-1' => [
                    'first_name' => 'No',
                    'last_name' => 'Portal',
                    'email' => 'no.portal@example.com',
                    'wants_portal_access' => false,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ClientContact::where('email', 'no.portal@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'no.portal@example.com')->exists())->toBeFalse();

    Bus::assertNotDispatched(SendClientPortalWelcomeEmail::class);
});

test('a blank email is required when portal access is enabled so no contact is silently missed', function () {
    Livewire::test(EditClient::class, ['record' => $this->client->getRouteKey()])
        ->fillForm([
            'contacts' => [
                'item-1' => [
                    'first_name' => 'Missing',
                    'last_name' => 'Email',
                    'email' => '',
                    'wants_portal_access' => true,
                ],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['contacts.item-1.email' => 'required']);
});

test('an email colliding with an existing user does not create a duplicate and does not block the contact from saving', function () {
    Bus::fake();

    User::factory()->create([
        'company_id' => $this->user->company_id,
        'email' => 'taken@example.com',
    ]);

    Livewire::test(EditClient::class, ['record' => $this->client->getRouteKey()])
        ->fillForm([
            'contacts' => [
                'item-1' => [
                    'first_name' => 'Taken',
                    'last_name' => 'Email',
                    'email' => 'taken@example.com',
                    'wants_portal_access' => true,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ClientContact::where('email', 'taken@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'taken@example.com')->count())->toBe(1);

    Bus::assertNotDispatched(SendClientPortalWelcomeEmail::class);
});

test('deleting a client contact deletes their linked portal account', function () {
    Bus::fake();

    $contact = $this->client->contacts()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Gone',
        'last_name' => 'Soon',
        'email' => 'gone.soon@example.com',
        'wants_portal_access' => true,
    ]);

    $user = User::where('client_contact_id', $contact->id)->firstOrFail();

    $contact->delete();

    expect(User::find($user->id))->toBeNull();
});

test('a manual create can be triggered on an existing contact regardless of the wants_portal_access flag', function () {
    Bus::fake();

    $contact = $this->client->contacts()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Manual',
        'last_name' => 'Later',
        'email' => 'manual.later@example.com',
        'wants_portal_access' => false,
    ]);

    expect(User::where('client_contact_id', $contact->id)->exists())->toBeFalse();

    CreateClientContactPortalAccount::run($contact);

    $user = User::where('client_contact_id', $contact->id)->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('manual.later@example.com')
        ->and($user->hasRole('client'))->toBeTrue();

    Bus::assertDispatched(SendClientPortalWelcomeEmail::class, fn ($job) => $job->contact->is($contact));
});

test('editing an existing contact without an email does not require one', function () {
    $contact = $this->client->contacts()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'No',
        'last_name' => 'Email',
        'email' => null,
        'wants_portal_access' => false,
    ]);

    Livewire::test(EditClient::class, ['record' => $this->client->getRouteKey()])
        ->fillForm([
            'contacts' => [
                "record-{$contact->id}" => [
                    'id' => $contact->id,
                    'first_name' => 'No',
                    'last_name' => 'Email Updated',
                    'email' => null,
                    'wants_portal_access' => false,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contact->fresh()->last_name)->toBe('Email Updated');
});
