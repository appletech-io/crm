<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('non-admin cannot access users resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/crm/users')->assertRedirect('/crm');
});

test('admin cannot access users resource', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/crm/users')->assertRedirect('/crm');
});

test('site_admin can access users resource', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    Livewire::test(ListUsers::class)->assertSuccessful();
});

test('users list excludes users with a candidate id', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    $candidateUser = User::factory()->create([
        'candidate_id' => 1,
        'candidate_type' => 'App\\Models\\EducationCandidate',
    ]);

    Livewire::test(ListUsers::class)
        ->assertCanNotSeeTableRecords([$candidateUser])
        ->assertCanSeeTableRecords([$siteAdmin]);
});

test('site_admin can create a user with a role', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New Consultant',
            'email' => 'consultant@example.com',
            'password' => 'password',
            'roles' => [Role::where('name', 'consultant')->first()->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('email', 'consultant@example.com')->first();
    expect($created)->not->toBeNull();
    expect($created->hasRole('consultant'))->toBeTrue();
});

test('site_admin can create a client role user linked to a client contact', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    $client = Client::factory()->create(['company_id' => $siteAdmin->company_id]);
    $contact = ClientContact::factory()->create([
        'company_id' => $siteAdmin->company_id,
        'client_id' => $client->id,
        'first_name' => 'Clare',
        'last_name' => 'Webster',
    ]);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Clare Webster',
            'email' => 'clare@example.com',
            'password' => 'password',
            'roles' => [Role::where('name', 'client')->first()->id],
        ])
        ->fillForm([
            'client_contact_id' => $contact->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('email', 'clare@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->hasRole('client'))->toBeTrue()
        ->and($created->client_contact_id)->toBe($contact->id)
        ->and($created->client()->id)->toBe($client->id);
});

test('the client contact field is hidden unless the client role is selected', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    Livewire::test(CreateUser::class)
        ->assertFormFieldIsHidden('client_contact_id')
        ->set('data.roles', [Role::where('name', 'client')->first()->id])
        ->assertFormFieldIsVisible('client_contact_id');
});

test('site_admin can edit a user role', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    $user = User::factory()->create(['company_id' => $siteAdmin->company_id]);
    $user->assignRole('resourcer');

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'roles' => [Role::where('name', 'consultant')->first()->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->hasRole('consultant'))->toBeTrue();
});
