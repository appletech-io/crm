<?php

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

test('the admin panel shows the logged-in users own company logo, not the default', function () {
    $this->seed(RoleSeeder::class);

    Storage::fake('local');
    Storage::disk('local')->put('company-logos/acme.png', 'fake logo contents');
    $company = Company::factory()->create(['logo' => 'company-logos/acme.png']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->get('/crm')
        ->assertOk()
        ->assertSee(route('company.logo', $company), false)
        ->assertDontSee(asset('images/appletech.png'), false);
});

test('the admin panel shows the default logo for a company with none uploaded', function () {
    $this->seed(RoleSeeder::class);

    $company = Company::factory()->create(['logo' => null]);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->get('/crm')
        ->assertOk()
        ->assertSee(asset('images/appletech.png'), false);
});

test('the client portal shows the logged-in client contacts own company logo', function () {
    $this->seed(RoleSeeder::class);

    Storage::fake('local');
    Storage::disk('local')->put('company-logos/acme.png', 'fake logo contents');
    $company = Company::factory()->create(['logo' => 'company-logos/acme.png']);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $contact = ClientContact::factory()->create(['company_id' => $company->id, 'client_id' => $client->id]);
    $user = User::factory()->create(['company_id' => $company->id, 'client_contact_id' => $contact->id]);
    $user->assignRole('client');
    $this->actingAs($user);

    $this->get('/client/my-bookings')
        ->assertOk()
        ->assertSee(route('company.logo', $company), false)
        ->assertDontSee(asset('images/appletech.png'), false);
});
