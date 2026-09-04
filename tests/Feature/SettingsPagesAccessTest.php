<?php

use App\Filament\Pages\CandidateSettings;
use App\Filament\Pages\ClientSettings;
use App\Filament\Pages\JobSettings;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->industry = Industry::factory()->create(['slug' => 'education']);
});

/** @return array<int, class-string> */
function settingsPages(): array
{
    return [ClientSettings::class, CandidateSettings::class, JobSettings::class];
}

test('an admin with an active industry can access every settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$admin->id}.active_industry_id", $this->industry->id);

    foreach (settingsPages() as $page) {
        expect($page::canAccess())->toBeTrue();
    }
});

test('a non-admin cannot access any settings page, even with an active industry', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    foreach (settingsPages() as $page) {
        expect($page::canAccess())->toBeFalse();
    }
});

test('a site admin cannot access any settings page, even with an active industry', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);
    Cache::put("user.{$siteAdmin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$siteAdmin->id}.active_industry_id", $this->industry->id);

    foreach (settingsPages() as $page) {
        expect($page::canAccess())->toBeFalse();
    }
});

test('an admin with no active industry cannot access any settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    foreach (settingsPages() as $page) {
        expect($page::canAccess())->toBeFalse();
    }
});

test('compliance can access candidate settings (for reference forms), but not client or job settings', function () {
    $compliance = User::factory()->create();
    $compliance->assignRole('compliance');
    $this->actingAs($compliance);
    Cache::put("user.{$compliance->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$compliance->id}.active_industry_id", $this->industry->id);

    expect(CandidateSettings::canAccess())->toBeTrue();
    expect(ClientSettings::canAccess())->toBeFalse();
    expect(JobSettings::canAccess())->toBeFalse();
});
