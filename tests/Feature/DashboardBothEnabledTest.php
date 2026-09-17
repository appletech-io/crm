<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Dashboards\ConstructionDashboard;
use App\Filament\Pages\Dashboards\NoBookingsDashboard;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->construction = Industry::factory()->create(['slug' => 'construction']);
    $this->it = Industry::factory()->create(['slug' => 'it']);
    $this->company->industries()->attach([
        $this->construction->id => ['uses_bookings' => true, 'uses_perm' => true],
        $this->it->id => ['uses_bookings' => true, 'uses_perm' => true],
    ]);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->industries()->attach([$this->construction->id, $this->it->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->setActiveIndustry = function (Industry $industry): void {
        Cache::put("user.{$this->admin->id}.active_industry", $industry->slug);
        Cache::put("user.{$this->admin->id}.active_industry_id", $industry->id);
    };
});

test('with both flags on and no preference set, the dashboard defaults to the bookings dashboard', function () {
    ($this->setActiveIndustry)($this->construction);

    $dashboard = new Dashboard;

    expect($dashboard->getWidgets())->toBe((new ConstructionDashboard)->getWidgets());
});

test('with both flags on and a perm preference, the dashboard resolves NoBookingsDashboard', function () {
    ($this->setActiveIndustry)($this->construction);
    $this->admin->update(['dashboard_view' => 'perm']);

    $dashboard = new Dashboard;

    expect($dashboard->getWidgets())->toBe((new NoBookingsDashboard)->getWidgets());
});

test('with both flags on but no bespoke industry dashboard class, it falls back to NoBookingsDashboard instead of blank', function () {
    ($this->setActiveIndustry)($this->it);

    $dashboard = new Dashboard;

    expect($dashboard->getWidgets())->toBe((new NoBookingsDashboard)->getWidgets());
});

test('the header toggle action is only shown when both flags are on', function () {
    ($this->setActiveIndustry)($this->construction);

    Livewire::test(Dashboard::class)->assertActionExists('switchDashboardView');

    $this->company->industries()->updateExistingPivot($this->construction->id, ['uses_perm' => false]);

    Livewire::test(Dashboard::class)->assertActionDoesNotExist('switchDashboardView');
});

test('clicking the toggle action persists the flip on the user record', function () {
    ($this->setActiveIndustry)($this->construction);

    Livewire::test(Dashboard::class)->callAction('switchDashboardView');

    expect($this->admin->fresh()->dashboard_view)->toBe('perm');

    Livewire::test(Dashboard::class)->callAction('switchDashboardView');

    expect($this->admin->fresh()->dashboard_view)->toBe('bookings');
});
