<?php

use App\Filament\Pages\Calendar;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('an admin can access the calendar page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(Calendar::canAccess())->toBeTrue();
});

test('a consultant can access the calendar page', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(Calendar::canAccess())->toBeTrue();
});

test('a site_admin cannot access the calendar page unless impersonating', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    expect(Calendar::canAccess())->toBeFalse();
});
