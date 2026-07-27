<?php

use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function actingAsIndustryUser(string $role, string $slug): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    $industry = Industry::factory()->create(['slug' => $slug]);
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    test()->actingAs($user);

    return $user;
}

test('consultants cannot see the delete action on an education candidate', function () {
    $user = actingAsIndustryUser('consultant', 'education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id, 'consultant_id' => $user->id]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionHidden('delete');
});

test('admins can see the delete action on an education candidate', function () {
    $user = actingAsIndustryUser('admin', 'education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionVisible('delete');
});

test('consultants cannot see the delete bulk action on the education candidates table', function () {
    actingAsIndustryUser('consultant', 'education');

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->assertTableBulkActionHidden('delete');
});

test('admins can see the delete bulk action on the education candidates table', function () {
    actingAsIndustryUser('admin', 'education');

    Livewire::test(ListEducationCandidates::class)
        ->set('activeSection', 'all')
        ->assertTableBulkActionVisible('delete');
});

test('consultants cannot see the delete action on a healthcare candidate', function () {
    $user = actingAsIndustryUser('consultant', 'healthcare');
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $user->company_id, 'consultant_id' => $user->id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionHidden('delete');
});

test('admins can see the delete action on a healthcare candidate', function () {
    $user = actingAsIndustryUser('admin', 'healthcare');
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionVisible('delete');
});

test('consultants cannot see the delete bulk action on the healthcare candidates table', function () {
    actingAsIndustryUser('consultant', 'healthcare');

    Livewire::test(ListHealthcareCandidates::class)
        ->assertTableBulkActionHidden('delete');
});

test('admins can see the delete bulk action on the healthcare candidates table', function () {
    actingAsIndustryUser('admin', 'healthcare');

    Livewire::test(ListHealthcareCandidates::class)
        ->assertTableBulkActionVisible('delete');
});
