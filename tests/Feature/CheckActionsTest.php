<?php

use App\Actions\Automations\CheckActions;
use App\Models\Action;
use App\Models\ActionTrigger;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\TodoItem;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->consultant = User::factory()->create(['company_id' => $this->company->id]);
});

test('creates a todo for the records consultant when conditions are satisfied', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    $action = Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
        'todo_name' => 'Follow up with client',
        'todo_description' => 'Client notes flagged a follow up.',
        'todo_priority' => 'high',
    ]);

    CheckActions::run($client);

    $todo = TodoItem::where('user_id', $this->consultant->id)->first();

    expect($todo)->not->toBeNull()
        ->and($todo->name)->toBe('Follow up with client')
        ->and($todo->description)->toBe('Client notes flagged a follow up.')
        ->and($todo->priority->value)->toBe('high')
        ->and($todo->model_type)->toBe(Client::class)
        ->and($todo->model_id)->toBe($client->id);

    expect(ActionTrigger::where('action_id', $action->id)
        ->where('model_type', Client::class)
        ->where('model_id', $client->id)
        ->where('todo_item_id', $todo->id)
        ->exists())->toBeTrue();
});

test('does not create a todo when conditions are not satisfied', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => null,
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    expect(TodoItem::count())->toBe(0);
});

test('does not fire again for the same record once already triggered', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);
    CheckActions::run($client);
    CheckActions::run($client);

    expect(TodoItem::count())->toBe(1);
});

test('closes the open trigger once the condition is no longer satisfied', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    $action = Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    $trigger = ActionTrigger::where('action_id', $action->id)->where('model_id', $client->id)->first();
    expect($trigger->isOpen())->toBeTrue();

    $client->update(['notes' => null]);
    CheckActions::run($client);

    expect($trigger->refresh()->isOpen())->toBeFalse()
        ->and(TodoItem::count())->toBe(1)
        ->and($trigger->todoItem->refresh()->isComplete())->toBeTrue();
});

test('does not overwrite a todo the consultant already completed themselves when resolving', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    $action = Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    $trigger = ActionTrigger::where('action_id', $action->id)->where('model_id', $client->id)->first();
    $completedAt = now()->subDays(3);
    $trigger->todoItem->update(['completed_at' => $completedAt]);

    $client->update(['notes' => null]);
    CheckActions::run($client);

    expect($trigger->todoItem->refresh()->completed_at->toDateTimeString())->toBe($completedAt->toDateTimeString());
});

test('fires again as a new occurrence once the condition becomes true again after resolving', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    $action = Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    // First occurrence.
    CheckActions::run($client);

    // Notes get cleared — the trigger resolves, no new todo yet.
    $client->update(['notes' => null]);
    CheckActions::run($client);

    // Notes get filled in again months later — a fresh occurrence.
    $client->update(['notes' => 'Needs another follow up call']);
    CheckActions::run($client);

    expect(TodoItem::count())->toBe(2);

    $triggers = ActionTrigger::where('action_id', $action->id)->where('model_id', $client->id)->orderBy('id')->get();
    expect($triggers)->toHaveCount(2)
        ->and($triggers->first()->isOpen())->toBeFalse()
        ->and($triggers->last()->isOpen())->toBeTrue();
});

test('does not create a todo when the record has no consultant', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => null,
        'notes' => 'Needs a follow up call',
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    expect(TodoItem::count())->toBe(0);
});

test('does not fire an inactive action', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
        'is_active' => false,
    ]);

    CheckActions::run($client);

    expect(TodoItem::count())->toBe(0);
});

test('does not fire an action belonging to another company', function () {
    $otherCompany = Company::factory()->create();

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    Action::factory()->create([
        'company_id' => $otherCompany->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    expect(TodoItem::count())->toBe(0);
});

test('does not fire a client action belonging to a different industry', function () {
    $otherIndustry = Industry::factory()->create(['slug' => 'healthcare']);

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $otherIndustry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    expect(TodoItem::count())->toBe(0);
});

test('fires for an education candidate', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Jane',
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => EducationCandidate::class,
        'conditions' => [
            ['field' => 'first_name', 'operator' => 'filled'],
        ],
        'todo_name' => 'Chase candidate documents',
    ]);

    CheckActions::run($candidate);

    $todo = TodoItem::where('user_id', $this->consultant->id)->first();

    expect($todo)->not->toBeNull()
        ->and($todo->name)->toBe('Chase candidate documents')
        ->and($todo->model_type)->toBe(EducationCandidate::class)
        ->and($todo->model_id)->toBe($candidate->id);
});

test('fires for a healthcare candidate', function () {
    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);

    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Jane',
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $healthcareIndustry->id,
        'model_type' => HealthcareCandidate::class,
        'conditions' => [
            ['field' => 'first_name', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($candidate);

    expect(TodoItem::where('model_type', HealthcareCandidate::class)->where('model_id', $candidate->id)->exists())->toBeTrue();
});

test('fires for a booking matching the actions sector', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Booking::class,
        'conditions' => [
            ['field' => 'status', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($booking);

    expect(TodoItem::where('model_type', Booking::class)->where('model_id', $booking->id)->exists())->toBeTrue();
});

test('does not fire a booking action configured for a different sector', function () {
    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $healthcareIndustry->id,
        'model_type' => Booking::class,
        'conditions' => [
            ['field' => 'status', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($booking);

    expect(TodoItem::count())->toBe(0);
});

test('saving a client through the observer triggers matching actions', function () {
    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
        'todo_name' => 'Follow up with client',
    ]);

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => null,
    ]);

    expect(TodoItem::count())->toBe(0);

    $client->update(['notes' => 'Needs a follow up call']);

    expect(TodoItem::where('user_id', $this->consultant->id)->where('name', 'Follow up with client')->exists())->toBeTrue();
});
