<?php

use App\Filament\Resources\JobStatuses\Pages\EditJobStatus;
use App\Filament\Resources\JobStatuses\Pages\ListJobStatuses;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create();
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('list page renders', function () {
    Livewire::test(ListJobStatuses::class)
        ->assertSuccessful();
});

test('can create a status', function () {
    Livewire::test(ListJobStatuses::class)
        ->callAction('create', data: ['name' => 'Shortlisting', 'color' => 'blue'])
        ->assertHasNoActionErrors();

    $status = JobStatus::where('name', 'Shortlisting')->first();
    expect($status)->not->toBeNull();
    expect($status->color)->toBe('blue');
    expect($status->company_id)->toBe($this->user->company_id);
    expect($status->industry_id)->toBe($this->industry->id);
});

test('creating a status requires a color', function () {
    Livewire::test(ListJobStatuses::class)
        ->callAction('create', data: ['name' => 'Shortlisting'])
        ->assertHasActionErrors(['color']);

    expect(JobStatus::where('name', 'Shortlisting')->exists())->toBeFalse();
});

test('edit page renders', function () {
    $status = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditJobStatus::class, ['record' => $status->getRouteKey()])
        ->assertSuccessful();
});

test('status name can be updated', function () {
    $status = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Old Name',
    ]);

    Livewire::test(EditJobStatus::class, ['record' => $status->getRouteKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($status->refresh()->name)->toBe('New Name');
});

test('status color can be updated', function () {
    $status = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'color' => 'green',
    ]);

    Livewire::test(EditJobStatus::class, ['record' => $status->getRouteKey()])
        ->fillForm(['color' => 'rose'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($status->refresh()->color)->toBe('rose');
});

test('a job status from another company or industry is not visible in the list', function () {
    $ownStatus = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $otherCompanyStatus = JobStatus::factory()->create();

    $otherIndustry = Industry::factory()->create();
    $otherIndustryStatus = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $otherIndustry->id,
    ]);

    Livewire::test(ListJobStatuses::class)
        ->assertCanSeeTableRecords([$ownStatus])
        ->assertCanNotSeeTableRecords([$otherCompanyStatus, $otherIndustryStatus]);
});
