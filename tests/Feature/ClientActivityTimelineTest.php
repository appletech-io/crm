<?php

use App\Filament\Resources\EducationClients\Pages\EditEducationClient;
use App\Filament\Widgets\ClientActivityTimeline;
use App\Models\ClientActivity;
use App\Models\EducationClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);
});

test('activity timeline widget renders', function () {
    $client = EducationClient::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(ClientActivityTimeline::class, ['record' => $client])
        ->assertSuccessful();
});

test('activity can be logged via action', function () {
    $client = EducationClient::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(ClientActivityTimeline::class, ['record' => $client])
        ->callTableAction('logActivity', data: [
            'type' => 'call',
            'note' => 'Called client, left voicemail',
        ])
        ->assertHasNoTableActionErrors();

    expect(ClientActivity::count())->toBe(1);
    $activity = ClientActivity::first();
    expect($activity->note)->toBe('Called client, left voicemail');
    expect($activity->type->value)->toBe('call');
    expect($activity->user_id)->toBe($this->user->id);
    expect($activity->model_type)->toBe(EducationClient::class);
    expect($activity->model_id)->toBe($client->id);
});

test('activity action requires type and note', function () {
    $client = EducationClient::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(ClientActivityTimeline::class, ['record' => $client])
        ->callTableAction('logActivity', data: [])
        ->assertHasTableActionErrors(['type', 'note']);
});

test('activities are paginated', function () {
    $client = EducationClient::factory()->create(['company_id' => $this->user->company_id]);

    foreach (range(1, 12) as $i) {
        $client->activities()->create([
            'user_id' => $this->user->id,
            'type' => 'note',
            'note' => "Activity {$i}",
        ]);
    }

    $component = Livewire::test(ClientActivityTimeline::class, ['record' => $client]);

    expect($component->instance()->getAllTableRecordsCount())->toBe(12)
        ->and($component->instance()->getTableRecords())->toHaveCount(10);
});

test('activity tab renders on edit page', function () {
    $client = EducationClient::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditEducationClient::class, ['record' => $client->getRouteKey()])
        ->assertSuccessful();
});
