<?php

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
});

test('the client portal header shows the school name in title case', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'WELFORD SCHOOL']);
    $contact = ClientContact::factory()->create(['company_id' => $this->company->id, 'client_id' => $client->id]);
    $user = User::factory()->create(['company_id' => $this->company->id, 'client_contact_id' => $contact->id]);
    $user->assignRole('client');

    $this->actingAs($user)
        ->get('/client/my-bookings')
        ->assertOk()
        ->assertSee('Welford School')
        ->assertDontSee('WELFORD SCHOOL');
});

test('the client portal header renders nothing for a user with no client', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($user);

    expect(trim(view('filament.client-portal-header')->render()))->toBe('');
});
