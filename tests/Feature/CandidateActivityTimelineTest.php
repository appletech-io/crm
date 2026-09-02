<?php

use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\TodoItems\TodoItemResource;
use App\Filament\Widgets\CandidateActivityTimeline;
use App\Models\CandidateActivity;
use App\Models\EducationCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);
});

test('activity timeline widget renders', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate])
        ->assertSuccessful();
});

test('activity can be logged via action', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate])
        ->callTableAction('logActivity', data: [
            'type' => 'call',
            'note' => 'Called candidate, left voicemail',
        ])
        ->assertHasNoTableActionErrors();

    expect(CandidateActivity::count())->toBe(1);
    $activity = CandidateActivity::first();
    expect($activity->note)->toBe('Called candidate, left voicemail');
    expect($activity->type->value)->toBe('call');
    expect($activity->user_id)->toBe($this->user->id);
    expect($activity->model_type)->toBe(EducationCandidate::class);
    expect($activity->model_id)->toBe($candidate->id);
});

test('create and todo logs the activity and redirects to a pre-filled create todo page', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    // Log & Todo is a real inline footer button, alongside Submit and
    // Cancel — both it and the primary submit button are type="submit",
    // triggering the same callMountedAction; Log & Todo just flags its
    // intent first (mirroring the x-on:click on its real button) so
    // logActivity's own action knows to redirect afterwards.
    Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate])
        ->mountTableAction('logActivity')
        ->set('mountedActions.0.data.type', 'call')
        ->set('mountedActions.0.data.note', 'Follow up next week')
        ->set('mountedActions.0.data.__intent', 'todo')
        ->call('callMountedAction')
        ->assertRedirect(TodoItemResource::getUrl('create', [
            'model_type' => EducationCandidate::class,
            'model_id' => $candidate->id,
            'name' => 'Follow up next week',
        ]));

    expect(CandidateActivity::count())->toBe(1);
    expect(CandidateActivity::first()->note)->toBe('Follow up next week');
});

test('create and todo requires type and note, same as log activity', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate])
        ->mountTableAction('logActivity')
        ->set('mountedActions.0.data.__intent', 'todo')
        ->call('callMountedAction')
        ->assertHasErrors([
            'mountedActions.0.data.type',
            'mountedActions.0.data.note',
        ]);

    expect(CandidateActivity::count())->toBe(0);
});

test('activity action requires type and note', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate])
        ->callTableAction('logActivity', data: [])
        ->assertHasTableActionErrors(['type', 'note']);
});

test('an interview can be logged against a candidate', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate])
        ->callTableAction('logActivity', data: [
            'type' => 'interview',
            'note' => 'Interviewed for Year 3 role',
        ])
        ->assertHasNoTableActionErrors();

    expect(CandidateActivity::where('type', 'interview')->exists())->toBeTrue();
});

test('BDM Call is not a loggable type on a candidate, since it belongs on clients', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate])
        ->callTableAction('logActivity', data: [
            'type' => 'bdm_call',
            'note' => 'Should not be allowed',
        ])
        ->assertHasTableActionErrors(['type']);
});

test('activities are paginated', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    foreach (range(1, 12) as $i) {
        $candidate->activities()->create([
            'user_id' => $this->user->id,
            'type' => 'note',
            'note' => "Activity {$i}",
        ]);
    }

    $component = Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate]);

    expect($component->instance()->getAllTableRecordsCount())->toBe(12)
        ->and($component->instance()->getTableRecords())->toHaveCount(10);
});

test('activity tab renders on edit page', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful();
});

test('activities can be filtered by type', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $call = $candidate->activities()->create([
        'user_id' => $this->user->id,
        'type' => 'call',
        'note' => 'Called candidate',
    ]);

    $note = $candidate->activities()->create([
        'user_id' => $this->user->id,
        'type' => 'note',
        'note' => 'Left a note',
    ]);

    Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate])
        ->filterTable('type', 'call')
        ->assertCanSeeTableRecords([$call])
        ->assertCanNotSeeTableRecords([$note]);
});
