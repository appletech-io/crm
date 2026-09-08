<?php

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('the admin panel ships the hover-expand behaviour for the sidebar', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->get('/crm')
        ->assertOk()
        // Delegated from document rather than bound to the element, so it
        // survives Livewire SPA navigation.
        ->assertSee("Alpine?.store('sidebar')", false)
        ->assertSee('.fi-main-sidebar', false);
});

test('the client portal is left alone', function () {
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
    $this->actingAs($user);

    // The client panel lands on a redirect to its first page, so follow it
    // through rather than asserting on the 302 itself.
    $this->followingRedirects()
        ->get('/client')
        ->assertOk()
        ->assertDontSee("Alpine?.store('sidebar')", false);
});
