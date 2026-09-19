<?php

use App\Filament\Pages\Dashboards\HealthcareDashboard;
use App\Filament\Widgets\CareLogsQuickLink;
use App\Models\CompanyIndustry;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->company = $this->admin->company;
    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company->industries()->attach($this->industry->id);

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);
});

test('the Healthcare dashboard does not include the Care Logs quick link when care_logging is off', function () {
    expect((new HealthcareDashboard)->getWidgets())->not->toContain(CareLogsQuickLink::class);
});

test('the Healthcare dashboard includes the Care Logs quick link once care_logging is on', function () {
    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['care_logging' => true]);

    expect((new HealthcareDashboard)->getWidgets())->toContain(CareLogsQuickLink::class);
});
