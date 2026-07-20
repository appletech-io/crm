<?php

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function makeClientPortalUser(): User
{
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $contact = ClientContact::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'client_contact_id' => $contact->id,
    ]);
    $user->assignRole('client');

    return $user;
}

test('a client can access the client panel', function () {
    $user = makeClientPortalUser();

    $this->actingAs($user)->get('/client/my-bookings')->assertOk();
});

test('a client is redirected to the client panel instead of hitting a 403 loop on the admin panel', function () {
    $user = makeClientPortalUser();

    $this->actingAs($user)->get('/crm')->assertRedirect('/client');
});

test('a client is redirected away from a deeper admin panel url too', function () {
    $user = makeClientPortalUser();

    $this->actingAs($user)->get('/crm/clients')->assertRedirect('/client');
});

test('a client is redirected to the client panel instead of the candidate panel', function () {
    $user = makeClientPortalUser();

    $this->actingAs($user)->get('/candidate')->assertRedirect('/client');
});

test('a staff user is redirected to the admin panel instead of hitting a 403 on the client panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/client')->assertRedirect('/crm');
});

test('a candidate is redirected to the candidate panel instead of the client panel', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('candidate');

    $this->actingAs($user)->get('/client')->assertRedirect('/candidate');
});
