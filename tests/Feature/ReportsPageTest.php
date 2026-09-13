<?php

use App\Filament\Pages\Reports;
use App\Filament\Pages\Reports\ConstructionReports;
use App\Filament\Pages\Reports\EducationReports;
use App\Filament\Pages\Reports\HealthcareReports;
use App\Filament\Pages\Reports\ItReports;
use App\Filament\Pages\Reports\NoSectorReports;
use App\Filament\Widgets\Reports\BookingRevenueChart;
use App\Filament\Widgets\Reports\TempBookingStats;
use App\Filament\Widgets\Reports\TopClientsTable;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('an admin can access the reports page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(Reports::canAccess())->toBeTrue();
});

test('a site admin cannot access the reports page', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    expect(Reports::canAccess())->toBeFalse();
});

test('a non-admin cannot access the reports page', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(Reports::canAccess())->toBeFalse();
});

test('it resolves the education reports class when education is the active sector', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$admin->id}.active_industry", 'education');

    $page = app(Reports::class);

    expect($page->getWidgets())->toBe((new EducationReports)->getWidgets());
});

test('it resolves the healthcare reports class when healthcare is the active sector', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$admin->id}.active_industry", 'healthcare');

    $page = app(Reports::class);

    expect($page->getWidgets())->toBe((new HealthcareReports)->getWidgets());
});

test('it resolves the construction reports class when construction is the active sector', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Industry::factory()->create(['slug' => 'construction']);
    Cache::put("user.{$admin->id}.active_industry", 'construction');

    $page = app(Reports::class);

    expect($page->getWidgets())->toBe((new ConstructionReports)->getWidgets());
});

test('it resolves the it reports class when it is the active sector, dropping every booking-revenue widget', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Industry::factory()->create(['slug' => 'it']);
    Cache::put("user.{$admin->id}.active_industry", 'it');

    $page = app(Reports::class);

    expect($page->getWidgets())->toBe((new ItReports)->getWidgets())
        ->and($page->getWidgets())->not->toContain(TempBookingStats::class)
        ->and($page->getWidgets())->not->toContain(BookingRevenueChart::class)
        ->and($page->getWidgets())->not->toContain(TopClientsTable::class);
});

test('it falls back to the no-sector reports when no industry is active', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Cache::forget("user.{$admin->id}.active_industry");

    $page = app(Reports::class);

    expect($page->getWidgets())->toBe((new NoSectorReports)->getWidgets());
});

test('the reports page renders successfully for an admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", 1);

    Livewire::test(Reports::class)->assertSuccessful();
});

test('the it reports page renders successfully for an admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $industry = Industry::factory()->create(['slug' => 'it']);
    Cache::put("user.{$admin->id}.active_industry", 'it');
    Cache::put("user.{$admin->id}.active_industry_id", $industry->id);

    Livewire::test(Reports::class)->assertSuccessful();
});
