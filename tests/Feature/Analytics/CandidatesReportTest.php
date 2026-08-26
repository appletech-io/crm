<?php

use App\Filament\Pages\Analytics\CandidatesReport;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('an admin can access the candidates report', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(CandidatesReport::canAccess())->toBeTrue();
});

test('a site admin cannot access the candidates report', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    expect(CandidatesReport::canAccess())->toBeFalse();
});

test('a non-admin cannot access the candidates report', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(CandidatesReport::canAccess())->toBeFalse();
});

test('it shows a placeholder when no sector is active', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Cache::forget("user.{$admin->id}.active_industry");

    Livewire::test(CandidatesReport::class)
        ->assertSuccessful()
        ->assertSee('Select a sector');
});

test('it renders successfully and counts candidates and placements for the active sector', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", $industry->id);

    $company = $admin->company;

    EducationCandidate::factory()->count(2)->create(['company_id' => $company->id]);

    $component = Livewire::test(CandidatesReport::class)->assertSuccessful();

    $stats = $component->instance()->stats();

    expect($stats['Candidates'])->toBe(2)
        ->and($stats['Placed'])->toBe(0);
});
