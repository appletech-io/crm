<?php

use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('a non-admin consultant only sees candidates assigned to them', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $ownCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $consultant->id,
    ]);

    $otherConsultantCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $this->user->id,
    ]);

    $unassignedCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListEducationCandidates::class)
        ->assertCanSeeTableRecords([$ownCandidate])
        ->assertCanNotSeeTableRecords([$otherConsultantCandidate, $unassignedCandidate]);
});

test('an admin sees all candidates regardless of consultant_id', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $consultant->id,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->assertCanSeeTableRecords([$candidate]);
});

test('a non-admin cannot directly open another consultants candidate edit page', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $otherConsultantCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $this->user->id,
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    expect(fn () => Livewire::test(EditEducationCandidate::class, ['record' => $otherConsultantCandidate->getRouteKey()]))
        ->toThrow(ModelNotFoundException::class);
});

test('the consultant filter is only visible to admins', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    Livewire::test(ListEducationCandidates::class)
        ->assertTableFilterVisible('consultant_id');

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListEducationCandidates::class)
        ->assertTableFilterHidden('consultant_id');
});
