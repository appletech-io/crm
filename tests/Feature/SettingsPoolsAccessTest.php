<?php

use App\Filament\Pages\CandidateSettings;
use App\Filament\Pages\ClientSettings;
use App\Filament\Widgets\CandidateSettingsOverview;
use App\Filament\Widgets\ClientSettingsOverview;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->industry = Industry::factory()->create();
});

function actingAsWithRole(string $role, Industry $industry): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    return $user;
}

test('a consultant can now access Client Settings, seeing only the Client Pools stat', function () {
    actingAsWithRole('consultant', $this->industry);

    expect(ClientSettings::canAccess())->toBeTrue();

    Livewire::test(ClientSettingsOverview::class)
        ->assertSuccessful()
        ->assertSee('Client Pools')
        ->assertDontSee('Contact Job Titles')
        ->assertDontSee('Client Types');
});

test('an admin sees every Client Settings stat', function () {
    actingAsWithRole('admin', $this->industry);

    Livewire::test(ClientSettingsOverview::class)
        ->assertSuccessful()
        ->assertSee('Client Pools')
        ->assertSee('Contact Job Titles')
        ->assertSee('Client Types');
});

test('a consultant can now access Candidate Settings, seeing only the Candidate Pools stat', function () {
    actingAsWithRole('consultant', $this->industry);

    expect(CandidateSettings::canAccess())->toBeTrue();

    Livewire::test(CandidateSettingsOverview::class)
        ->assertSuccessful()
        ->assertSee('Candidate Pools')
        ->assertDontSee('Skills')
        ->assertDontSee('Job Titles')
        ->assertDontSee('Reference Forms')
        ->assertDontSee('Sample Profiles');
});

test('a compliance user sees Candidate Pools and Reference Forms, but not the admin-only config stats', function () {
    actingAsWithRole('compliance', $this->industry);

    expect(CandidateSettings::canAccess())->toBeTrue();

    Livewire::test(CandidateSettingsOverview::class)
        ->assertSuccessful()
        ->assertSee('Candidate Pools')
        ->assertSee('Reference Forms')
        ->assertDontSee('Skills')
        ->assertDontSee('Job Titles');
});

test('an admin sees every Candidate Settings stat', function () {
    actingAsWithRole('admin', $this->industry);

    Livewire::test(CandidateSettingsOverview::class)
        ->assertSuccessful()
        ->assertSee('Skills')
        ->assertSee('Candidate Statuses')
        ->assertSee('Candidate Pools')
        ->assertSee('Job Titles')
        ->assertSee('Qualifications')
        ->assertSee('Allowed Job Titles')
        ->assertSee('Reference Forms')
        ->assertSee('Sample Profiles');
});

test('a resourcer still cannot access Client or Candidate Settings', function () {
    actingAsWithRole('resourcer', $this->industry);

    expect(ClientSettings::canAccess())->toBeFalse()
        ->and(CandidateSettings::canAccess())->toBeFalse();
});
