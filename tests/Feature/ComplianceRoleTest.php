<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Dashboards\ComplianceEducationDashboard;
use App\Filament\Pages\Dashboards\ComplianceHealthcareDashboard;
use App\Filament\Pages\Dashboards\EducationDashboard;
use App\Filament\Pages\Dashboards\HealthcareDashboard;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function actAsComplianceUser(string $industrySlug, array $extraRoles = []): User
{
    $user = User::factory()->create();
    $user->assignRole('compliance');
    foreach ($extraRoles as $role) {
        $user->assignRole($role);
    }

    test()->actingAs($user);

    $industry = Industry::factory()->create(['slug' => $industrySlug]);
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    return $user;
}

test('isComplianceOnly is true when compliance is the users only role', function () {
    $user = User::factory()->create();
    $user->assignRole('compliance');

    expect($user->isComplianceOnly())->toBeTrue();
});

test('isComplianceOnly is false when the user has an additional role', function () {
    $user = User::factory()->create();
    $user->assignRole('compliance');
    $user->assignRole('consultant');

    expect($user->isComplianceOnly())->toBeFalse();
});

test('isComplianceOnly is false for a user without the compliance role', function () {
    $user = User::factory()->create();
    $user->assignRole('consultant');

    expect($user->isComplianceOnly())->toBeFalse();
});

test('compliance-only users cannot view bookings or clients', function () {
    actAsComplianceUser('education');

    expect(BookingResource::canViewAny())->toBeFalse();
    expect(ClientResource::canViewAny())->toBeFalse();
});

test('a compliance user with an additional role can still view bookings and clients', function () {
    actAsComplianceUser('education', ['consultant']);

    expect(BookingResource::canViewAny())->toBeTrue();
    expect(ClientResource::canViewAny())->toBeTrue();
});

test('the dashboard shows the compliance education dashboard for a compliance-only user', function () {
    actAsComplianceUser('education');

    $dashboard = (new Dashboard)->getWidgets();

    expect($dashboard)->toEqual((new ComplianceEducationDashboard)->getWidgets());
});

test('the dashboard shows the compliance healthcare dashboard for a compliance-only user', function () {
    actAsComplianceUser('healthcare');

    $dashboard = (new Dashboard)->getWidgets();

    expect($dashboard)->toEqual((new ComplianceHealthcareDashboard)->getWidgets());
});

test('the dashboard falls back to the standard education dashboard when the user has more than the compliance role', function () {
    actAsComplianceUser('education', ['consultant']);

    $dashboard = (new Dashboard)->getWidgets();

    expect($dashboard)->toEqual((new EducationDashboard)->getWidgets());
});

test('the dashboard falls back to the standard healthcare dashboard when the user has more than the compliance role', function () {
    actAsComplianceUser('healthcare', ['consultant']);

    $dashboard = (new Dashboard)->getWidgets();

    expect($dashboard)->toEqual((new HealthcareDashboard)->getWidgets());
});

test('the compliance dashboard uses a three column grid so its vetting tables sit side by side', function () {
    actAsComplianceUser('education');

    expect((new Dashboard)->getColumns())->toBe(3);
});

test('the standard dashboard uses a two column grid', function () {
    actAsComplianceUser('education', ['consultant']);

    expect((new Dashboard)->getColumns())->toBe(2);
});
