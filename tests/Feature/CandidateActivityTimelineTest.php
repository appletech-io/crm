<?php

use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
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
        ->callAction('logActivity', data: [
            'type' => 'call',
            'note' => 'Called candidate, left voicemail',
        ])
        ->assertHasNoActionErrors();

    expect(CandidateActivity::count())->toBe(1);
    $activity = CandidateActivity::first();
    expect($activity->note)->toBe('Called candidate, left voicemail');
    expect($activity->type->value)->toBe('call');
    expect($activity->user_id)->toBe($this->user->id);
    expect($activity->model_type)->toBe(EducationCandidate::class);
    expect($activity->model_id)->toBe($candidate->id);
});

test('activity action requires type and note', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(CandidateActivityTimeline::class, ['record' => $candidate])
        ->callAction('logActivity', data: [])
        ->assertHasActionErrors(['type', 'note']);
});

test('activity tab renders on edit page', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful();
});
