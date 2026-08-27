<?php

use App\Filament\Resources\ComplianceItemJobTitles\Pages\ListComplianceItemJobTitles;
use App\Models\ComplianceItem;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
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

test('a non admin cannot view the required job titles list', function () {
    $user = User::factory()->create();
    Cache::put("user.{$user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $this->industry->id);

    $this->actingAs($user)->get('/crm/compliance-item-job-titles')->assertRedirect('/crm');
});

test('list page renders', function () {
    Livewire::test(ListComplianceItemJobTitles::class)
        ->assertSuccessful();
});

test('list only shows compliance items for the active company and industry', function () {
    $ownItem = ComplianceItem::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $otherItem = ComplianceItem::factory()->create();

    Livewire::test(ListComplianceItemJobTitles::class)
        ->assertCanSeeTableRecords([$ownItem])
        ->assertCanNotSeeTableRecords([$otherItem]);
});

test('list shows the required job titles for each compliance item', function () {
    $item = ComplianceItem::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $carer = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Carer',
    ]);

    $item->jobTitles()->attach($carer->id, [
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListComplianceItemJobTitles::class)
        ->assertSee('Carer');
});

test('a compliance item with no required job titles shows a placeholder', function () {
    ComplianceItem::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListComplianceItemJobTitles::class)
        ->assertSee('None configured');
});

test('the manage action opens as a modal, pre-filled with the current required job titles', function () {
    $item = ComplianceItem::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $carer = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $item->jobTitles()->attach($carer->id, [
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListComplianceItemJobTitles::class)
        ->mountAction(TestAction::make('manageRequiredJobTitles')->table($item))
        ->assertMountedActionModalSee($item->name)
        ->assertActionDataSet(['job_title_ids' => [$carer->id]]);
});

test('multiple job titles can be required for a compliance item in one go, via the modal', function () {
    $item = ComplianceItem::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $carer = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $seniorCarer = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListComplianceItemJobTitles::class)
        ->mountAction(TestAction::make('manageRequiredJobTitles')->table($item))
        ->set('mountedActions.0.data.job_title_ids', [$carer->id, $seniorCarer->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $requiredIds = $item->jobTitles()->pluck('job_titles.id')->sort()->values()->all();

    expect($requiredIds)->toBe(collect([$carer->id, $seniorCarer->id])->sort()->values()->all());

    $pivot = $item->jobTitles()->first()->pivot;
    expect($pivot->company_id)->toBe($this->user->company_id);
    expect($pivot->industry_id)->toBe($this->industry->id);
});

test('deselecting a job title removes it from the required list', function () {
    $item = ComplianceItem::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $carer = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $seniorCarer = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $item->jobTitles()->attach([$carer->id, $seniorCarer->id], [
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListComplianceItemJobTitles::class)
        ->mountAction(TestAction::make('manageRequiredJobTitles')->table($item))
        ->set('mountedActions.0.data.job_title_ids', [$carer->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($item->jobTitles()->pluck('job_titles.id')->all())->toBe([$carer->id]);
});
