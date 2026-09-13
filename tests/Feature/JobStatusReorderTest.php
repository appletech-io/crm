<?php

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

test('reordering statuses persists the new sort_order values', function () {
    $open = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'sort_order' => 0,
    ]);
    $onHold = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'sort_order' => 1,
    ]);
    $filled = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'sort_order' => 2,
    ]);

    Livewire::test(ListJobStatuses::class)
        ->call('reorderTable', [$filled->getKey(), $open->getKey(), $onHold->getKey()]);

    expect($filled->refresh()->sort_order)->toBe(1);
    expect($open->refresh()->sort_order)->toBe(2);
    expect($onHold->refresh()->sort_order)->toBe(3);
});

test('the table defaults to sort_order, not creation order', function () {
    $second = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'sort_order' => 1,
    ]);
    $first = JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'sort_order' => 0,
    ]);

    Livewire::test(ListJobStatuses::class)
        ->assertCanSeeTableRecords([$first, $second], inOrder: true);
});

test('a newly created status is appended to the end of the order', function () {
    JobStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'sort_order' => 5,
    ]);

    Livewire::test(ListJobStatuses::class)
        ->callAction('create', data: ['name' => 'Shortlisting', 'color' => 'blue'])
        ->assertHasNoActionErrors();

    $status = JobStatus::where('name', 'Shortlisting')->first();
    expect($status->sort_order)->toBe(6);
});

test('the first status created for a company/industry gets sort_order zero', function () {
    Livewire::test(ListJobStatuses::class)
        ->callAction('create', data: ['name' => 'Open', 'color' => 'green'])
        ->assertHasNoActionErrors();

    $status = JobStatus::where('name', 'Open')->first();
    expect($status->sort_order)->toBe(0);
});
