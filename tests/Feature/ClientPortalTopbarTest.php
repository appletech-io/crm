<?php

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Ashlawn School',
    ]);

    $this->contact = ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'wants_portal_access' => false,
    ]);

    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'client_contact_id' => $this->contact->id,
    ]);
    $this->user->assignRole('client');
});

test('the clients name is shown in the topbar and persists across client panel pages', function () {
    $this->actingAs($this->user);

    $this->get('/client/my-bookings')->assertSee('Ashlawn School');
    $this->get('/client/rate-bookings')->assertSee('Ashlawn School');
    $this->get('/client/my-candidates')->assertSee('Ashlawn School');
});

test('it shows nothing for a user with no linked client', function () {
    $staffUser = User::factory()->create(['company_id' => $this->company->id]);
    $staffUser->assignRole('admin');

    $this->actingAs($staffUser);

    $this->get('/crm')->assertDontSee('Ashlawn School');
});
