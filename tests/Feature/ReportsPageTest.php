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

test('a consultant can access the reports page, seeing only their own data', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(Reports::canAccess())->toBeTrue();
});

test('a resourcer cannot access the reports page', function () {
    $resourcer = User::factory()->create();
    $resourcer->assignRole('resourcer');
    $this->actingAs($resourcer);

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

test('the consultant filter on the filters form is hidden from a consultant but visible to an admin', function () {
    Industry::factory()->create(['slug' => 'education']);

    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');

    Livewire::test(Reports::class)
        ->assertFormFieldHidden('consultant_id', 'filtersForm');

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", 'education');

    Livewire::test(Reports::class)
        ->assertFormFieldVisible('consultant_id', 'filtersForm');
});

function callFilterConsultantId(TempBookingStats $widget): ?int
{
    $method = new ReflectionMethod($widget, 'filterConsultantId');

    return $method->invoke($widget);
}

test('ReadsReportFilters forces a non-admin\'s own id regardless of page filter state, but lets an admin choose', function () {
    $widget = new TempBookingStats;

    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    // Even with another consultant's id sitting in filter state (e.g. a
    // stale value from before the field was hidden), a non-admin's own id
    // always wins.
    $widget->pageFilters = ['consultant_id' => 99999];
    expect(callFilterConsultantId($widget))->toBe($consultant->id);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $widget->pageFilters = ['consultant_id' => 42];
    expect(callFilterConsultantId($widget))->toBe(42);

    $widget->pageFilters = [];
    expect(callFilterConsultantId($widget))->toBeNull();
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
