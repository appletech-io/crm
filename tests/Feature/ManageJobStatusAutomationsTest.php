<?php

use App\Filament\Resources\JobStatuses\Pages\ManageJobStatusAutomations;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobStatusAutomation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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

test('can create an automation with a filled condition from the suggestion list', function () {
    $open = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Open',
    ]);

    $filled = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Filled',
    ]);

    Livewire::test(ManageJobStatusAutomations::class)
        ->callAction('create', data: [
            'job_status_id' => $open->id,
            'to_job_status_id' => $filled->id,
            'conditions' => [
                'item-1' => ['field' => 'filled_at', 'operator' => 'filled'],
            ],
        ])
        ->assertHasNoActionErrors();

    $automation = JobStatusAutomation::where('job_status_id', $open->id)->first();

    expect($automation)->not->toBeNull();
    expect($automation->conditions)->toBe([
        ['field' => 'filled_at', 'operator' => 'filled'],
    ]);
});

test('can create an automation with an equals condition and a value', function () {
    $open = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Open',
    ]);

    $onHold = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'On Hold',
    ]);

    Livewire::test(ManageJobStatusAutomations::class)
        ->callAction('create', data: [
            'job_status_id' => $open->id,
            'to_job_status_id' => $onHold->id,
            'conditions' => [
                'item-1' => ['field' => 'title', 'operator' => 'equals', 'value' => 'Year 3 Class Teacher'],
            ],
        ])
        ->assertHasNoActionErrors();

    $automation = JobStatusAutomation::where('job_status_id', $open->id)->first();

    expect($automation)->not->toBeNull();
    expect($automation->conditions)->toBe([
        ['field' => 'title', 'operator' => 'equals', 'value' => 'Year 3 Class Teacher'],
    ]);
});

test('cannot create an automation with a field that is not in the suggestion list', function () {
    $open = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Open',
    ]);

    $filled = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Filled',
    ]);

    Livewire::test(ManageJobStatusAutomations::class)
        ->callAction('create', data: [
            'job_status_id' => $open->id,
            'to_job_status_id' => $filled->id,
            'conditions' => [
                'item-1' => ['field' => 'made_up_field_that_does_not_exist', 'operator' => 'filled'],
            ],
        ])
        ->assertHasActionErrors(['conditions.item-1.field']);
});

test('cannot create an equals condition without a value', function () {
    $open = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Open',
    ]);

    $filled = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Filled',
    ]);

    Livewire::test(ManageJobStatusAutomations::class)
        ->callAction('create', data: [
            'job_status_id' => $open->id,
            'to_job_status_id' => $filled->id,
            'conditions' => [
                'item-1' => ['field' => 'title', 'operator' => 'equals', 'value' => ''],
            ],
        ])
        ->assertHasActionErrors(['conditions.item-1.value']);
});

test('automations table only shows automations for the active company and industry', function () {
    $open = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Open',
    ]);

    $filled = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Filled',
    ]);

    $ownAutomation = JobStatusAutomation::factory()->create([
        'job_status_id' => $open->id,
        'to_job_status_id' => $filled->id,
        'conditions' => [['field' => 'filled_at', 'operator' => 'filled']],
    ]);

    $otherAutomation = JobStatusAutomation::factory()->create();

    Livewire::test(ManageJobStatusAutomations::class)
        ->assertCanSeeTableRecords([$ownAutomation])
        ->assertCanNotSeeTableRecords([$otherAutomation]);
});
