<?php

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a user who does not require account setup can access the CRM', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/crm')->assertOk();
});

test('a user who requires account setup is redirected to the security page from the CRM', function () {
    $user = User::factory()->create(['requires_account_setup' => true]);

    $this->actingAs($user)->get('/crm')->assertRedirect(route('security.edit'));
});

test('a user who requires account setup is redirected to the security page from the client panel', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $contact = ClientContact::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'client_contact_id' => $contact->id,
        'requires_account_setup' => true,
    ]);
    $user->assignRole('client');

    $this->actingAs($user)->get('/client')->assertRedirect(route('security.edit'));
});

test('having changed password but not set up two factor still requires setup', function () {
    $user = User::factory()->create([
        'requires_account_setup' => true,
        'password_changed_at' => now(),
    ]);

    expect($user->mustCompleteAccountSetup())->toBeTrue();

    $this->actingAs($user)->get('/crm')->assertRedirect(route('security.edit'));
});

test('having set up two factor but not changed password still requires setup', function () {
    $user = User::factory()->create([
        'requires_account_setup' => true,
        'two_factor_confirmed_at' => now(),
    ]);

    expect($user->mustCompleteAccountSetup())->toBeTrue();

    $this->actingAs($user)->get('/crm')->assertRedirect(route('security.edit'));
});

test('completing both password reset and two factor setup lifts the restriction', function () {
    $user = User::factory()->create([
        'requires_account_setup' => true,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);

    expect($user->mustCompleteAccountSetup())->toBeFalse();

    $this->actingAs($user)->get('/crm')->assertOk();
});

test('the security settings page itself remains reachable while setup is incomplete', function () {
    $user = User::factory()->create(['requires_account_setup' => true]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk();
});

test('a user can still log out while setup is incomplete', function () {
    $user = User::factory()->create(['requires_account_setup' => true]);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('a user with no account setup requirement is unaffected regardless of missing timestamps', function () {
    $user = User::factory()->create([
        'requires_account_setup' => false,
        'password_changed_at' => null,
        'two_factor_confirmed_at' => null,
    ]);

    expect($user->mustCompleteAccountSetup())->toBeFalse();

    $this->actingAs($user)->get('/crm')->assertOk();
});
