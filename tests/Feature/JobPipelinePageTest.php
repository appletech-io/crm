<?php

use App\Filament\Pages\JobPipeline;
use App\Filament\Widgets\JobPipelineFlow;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->construction = Industry::factory()->create(['slug' => 'construction']);
    $this->it = Industry::factory()->create(['slug' => 'it']);
    $this->company->industries()->attach([
        $this->construction->id => ['uses_bookings' => true],
        $this->it->id => ['uses_bookings' => false],
    ]);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach([$this->construction->id, $this->it->id]);
    $this->user->assignRole('consultant');
    $this->actingAs($this->user);

    $this->setActiveIndustry = function (Industry $industry): void {
        Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
        Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);
    };
});

test('the page is accessible for an industry with bookings toggled off', function () {
    ($this->setActiveIndustry)($this->it);

    expect(JobPipeline::canAccess())->toBeTrue();
});

test('the page is also accessible for an industry with bookings toggled on', function () {
    ($this->setActiveIndustry)($this->construction);

    expect(JobPipeline::canAccess())->toBeTrue();
});

test('the page is not accessible for a user with no active industry', function () {
    expect(JobPipeline::canAccess())->toBeFalse();
});

test('the page is accessible for a plain consultant, not just admins', function () {
    ($this->setActiveIndustry)($this->it);

    expect($this->user->hasRole('admin'))->toBeFalse()
        ->and(JobPipeline::canAccess())->toBeTrue();
});

test('the page renders successfully and hosts the job pipeline flow widget', function () {
    ($this->setActiveIndustry)($this->it);

    Livewire::test(JobPipeline::class)->assertSuccessful();

    expect((new JobPipeline)->getWidgets())->toBe([
        JobPipelineFlow::class,
    ]);
});
