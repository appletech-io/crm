<?php

use App\Filament\Resources\QualificationJobTitles\Pages\ListQualificationJobTitles;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Qualification;
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

test('a non admin cannot view the allowed job titles list', function () {
    $user = User::factory()->create();
    Cache::put("user.{$user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $this->industry->id);

    $this->actingAs($user)->get('/crm/qualification-job-titles')->assertRedirect('/crm');
});

test('list page renders', function () {
    Livewire::test(ListQualificationJobTitles::class)
        ->assertSuccessful();
});

test('list only shows qualifications for the active company and industry', function () {
    $ownQualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $otherQualification = Qualification::factory()->create();

    Livewire::test(ListQualificationJobTitles::class)
        ->assertCanSeeTableRecords([$ownQualification])
        ->assertCanNotSeeTableRecords([$otherQualification]);
});

test('list shows the allowed job titles for each qualification', function () {
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $teacher = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Teacher',
    ]);

    $qualification->jobTitles()->attach($teacher->id, [
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListQualificationJobTitles::class)
        ->assertSee('Teacher');
});

test('a qualification with no allowed job titles shows a placeholder', function () {
    Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListQualificationJobTitles::class)
        ->assertSee('None configured');
});

test('the manage action opens as a modal, not a page, and is pre-filled with the current allowed job titles', function () {
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $teacher = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $qualification->jobTitles()->attach($teacher->id, [
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListQualificationJobTitles::class)
        ->mountAction(TestAction::make('manageAllowedJobTitles')->table($qualification))
        ->assertMountedActionModalSee($qualification->name)
        ->assertActionDataSet(['job_title_ids' => [$teacher->id]]);
});

/**
 * These two tests set state via the mounted action's raw statePath
 * (`mountedActions.0.data.*`) rather than `callAction(..., data: [...])` —
 * the latter merges into the multi-select's pre-hydrated value instead of
 * replacing it, so a deselect (e.g. going from 2 selected to 1) silently
 * has no effect in tests. Real browser interaction is unaffected — this is
 * purely a testing-harness quirk with pre-filled multiple() selects.
 */
test('multiple job titles can be allowed for a qualification in one go, via the modal', function () {
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $teacher = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $teachingAssistant = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListQualificationJobTitles::class)
        ->mountAction(TestAction::make('manageAllowedJobTitles')->table($qualification))
        ->set('mountedActions.0.data.job_title_ids', [$teacher->id, $teachingAssistant->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $allowedIds = $qualification->jobTitles()->pluck('job_titles.id')->sort()->values()->all();

    expect($allowedIds)->toBe(collect([$teacher->id, $teachingAssistant->id])->sort()->values()->all());

    $pivot = $qualification->jobTitles()->first()->pivot;
    expect($pivot->company_id)->toBe($this->user->company_id);
    expect($pivot->industry_id)->toBe($this->industry->id);
});

test('deselecting a job title removes it from the allowed list', function () {
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $teacher = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $teachingAssistant = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $qualification->jobTitles()->attach([$teacher->id, $teachingAssistant->id], [
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(ListQualificationJobTitles::class)
        ->mountAction(TestAction::make('manageAllowedJobTitles')->table($qualification))
        ->set('mountedActions.0.data.job_title_ids', [$teacher->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($qualification->jobTitles()->pluck('job_titles.id')->all())->toBe([$teacher->id]);
});
