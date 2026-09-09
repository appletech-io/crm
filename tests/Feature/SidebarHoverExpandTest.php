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

/**
 * The chevrons are hidden with CSS, which no HTTP assertion can see. What
 * these guard is the pairing: if a Filament upgrade renames the classes,
 * the rules would silently stop applying and the buttons would come back.
 */
test('the classes our CSS hides are the ones Filament still renders', function () {
    $topbar = file_get_contents(base_path('vendor/filament/filament/resources/views/livewire/topbar.blade.php'));
    $sidebar = file_get_contents(base_path('vendor/filament/filament/resources/views/livewire/sidebar.blade.php'));

    // Both chevrons must stay inside the container we hide — if Filament
    // ever moves one out of it, hiding the container stops being enough.
    expect($topbar)->toMatch('/fi-topbar-collapse-sidebar-btn-ctn.*fi-topbar-open-collapse-sidebar-btn.*fi-topbar-close-collapse-sidebar-btn/s')
        ->and($sidebar)->toContain('fi-sidebar');
});

test('the admin theme hides every collapse chevron but leaves the mobile hamburger', function () {
    // Comments are stripped first: the block explaining these rules names
    // the hamburger class in prose, which a plain substring check would
    // read as a selector.
    $theme = preg_replace('#/\*.*?\*/#s', '', file_get_contents(base_path('resources/css/filament/admin/theme.css')));

    expect($theme)->toContain('.fi-topbar-collapse-sidebar-btn-ctn')
        // .fi-topbar-open-sidebar-btn is the mobile hamburger — it must
        // not be caught by this rule.
        ->and($theme)->not->toContain('.fi-topbar-open-sidebar-btn');
});
