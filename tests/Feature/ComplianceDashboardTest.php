<?php

use App\Filament\Pages\ComplianceDashboard;
use App\Filament\Pages\Dashboard;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('an admin can access the compliance dashboard page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", 1);

    expect(ComplianceDashboard::canAccess())->toBeTrue();

    Livewire::test(ComplianceDashboard::class)->assertSuccessful();
});

test('a non-admin consultant cannot access the compliance dashboard page', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", 1);

    expect(ComplianceDashboard::canAccess())->toBeFalse();
});

test('a compliance-only user cannot access the separate compliance dashboard page', function () {
    // They already see the compliance view on the regular Dashboard — this
    // second nav item is specifically the admin's way to reach it without
    // losing their own consultant-facing dashboard.
    $complianceUser = User::factory()->create();
    $complianceUser->assignRole('compliance');
    $this->actingAs($complianceUser);
    Cache::put("user.{$complianceUser->id}.active_industry", 'education');
    Cache::put("user.{$complianceUser->id}.active_industry_id", 1);

    expect(ComplianceDashboard::canAccess())->toBeFalse();
});

test('the compliance dashboard shows the compliance view for the active industry', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", 1);

    $page = new ComplianceDashboard;

    expect($page->getTitle())->toBe('Compliance')
        ->and($page->getWidgets())->not->toBeEmpty();
});

test('an admin still sees their own regular dashboard alongside the compliance dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", 1);

    expect(Dashboard::canAccess())->toBeTrue()
        ->and(ComplianceDashboard::canAccess())->toBeTrue();

    $dashboard = new Dashboard;

    // An admin (not compliance-only) still gets the consultant-facing
    // dashboard here, not the compliance one — that's what the separate
    // Compliance Dashboard nav item is for.
    expect($dashboard->getTitle())->not->toBe('Compliance');
});
