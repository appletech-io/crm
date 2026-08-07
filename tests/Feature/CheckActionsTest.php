<?php

use App\Actions\Automations\CheckActions;
use App\Enums\ActionEmailRecipient;
use App\Enums\EmailTemplateType;
use App\Jobs\SendCustomTemplateEmail;
use App\Models\Action;
use App\Models\ActionTrigger;
use App\Models\Booking;
use App\Models\CandidateReference;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\TodoItem;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Bus;

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

    $trigger = ActionTrigger::where('action_id', $action->id)
        ->where('model_type', Client::class)
        ->where('model_id', $client->id)
        ->first();

    expect($trigger)->not->toBeNull()
        ->and($trigger->todoItems->pluck('id')->all())->toBe([$todo->id]);
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
        ->and($trigger->todoItems->first()->refresh()->isComplete())->toBeTrue();
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
    $trigger->todoItems->first()->update(['completed_at' => $completedAt]);

    $client->update(['notes' => null]);
    CheckActions::run($client);

    expect($trigger->todoItems->first()->refresh()->completed_at->toDateTimeString())->toBe($completedAt->toDateTimeString());
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

test('fires for a vacancy', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'consultant_id' => $this->consultant->id,
        'title' => 'Year 3 Class Teacher',
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Vacancy::class,
        'conditions' => [
            ['field' => 'title', 'operator' => 'filled'],
        ],
        'todo_name' => 'Chase vacancy details',
    ]);

    CheckActions::run($vacancy);

    $todo = TodoItem::where('user_id', $this->consultant->id)->first();

    expect($todo)->not->toBeNull()
        ->and($todo->name)->toBe('Chase vacancy details')
        ->and($todo->model_type)->toBe(Vacancy::class)
        ->and($todo->model_id)->toBe($vacancy->id);
});

test('does not fire a vacancy action configured for a different industry than its client', function () {
    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'consultant_id' => $this->consultant->id,
        'title' => 'Year 3 Class Teacher',
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $healthcareIndustry->id,
        'model_type' => Vacancy::class,
        'conditions' => [
            ['field' => 'title', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($vacancy);

    expect(TodoItem::count())->toBe(0);
});

test('fires for a candidate reference, assigning the todo to the candidates consultant', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
    ]);

    $reference = $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'status' => 'rejected',
        'consent_to_contact' => true,
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => CandidateReference::class,
        'conditions' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'rejected'],
        ],
        'todo_name' => 'Chase an alternative reference',
    ]);

    CheckActions::run($reference);

    $todo = TodoItem::where('user_id', $this->consultant->id)->first();

    expect($todo)->not->toBeNull()
        ->and($todo->name)->toBe('Chase an alternative reference')
        ->and($todo->model_type)->toBe(CandidateReference::class)
        ->and($todo->model_id)->toBe($reference->id);
});

test('does not fire a candidate reference action configured for a different industry than its candidate', function () {
    $healthcareIndustry = Industry::factory()->create(['slug' => 'healthcare']);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
    ]);

    $reference = $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'status' => 'rejected',
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $healthcareIndustry->id,
        'model_type' => CandidateReference::class,
        'conditions' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'rejected'],
        ],
    ]);

    CheckActions::run($reference);

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

test('saving a vacancy through the observer triggers matching actions', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Vacancy::class,
        'conditions' => [
            ['field' => 'title', 'operator' => 'filled'],
        ],
        'todo_name' => 'Chase vacancy details',
    ]);

    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'consultant_id' => $this->consultant->id,
        'title' => 'Year 3 Class Teacher',
    ]);

    expect(TodoItem::where('user_id', $this->consultant->id)->where('name', 'Chase vacancy details')->exists())->toBeTrue();
});

test('saving a candidate reference through the observer triggers matching actions', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
    ]);

    Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => CandidateReference::class,
        'conditions' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'rejected'],
        ],
        'todo_name' => 'Chase an alternative reference',
    ]);

    $reference = $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'status' => 'pending',
    ]);

    expect(TodoItem::count())->toBe(0);

    $reference->update(['status' => 'rejected']);

    expect(TodoItem::where('user_id', $this->consultant->id)->where('name', 'Chase an alternative reference')->exists())->toBeTrue();
});

test('a role-based action creates a todo for every user with that role in the same company and industry', function () {
    $this->seed(RoleSeeder::class);

    $compliance1 = User::factory()->create(['company_id' => $this->company->id]);
    $compliance1->industries()->attach($this->industry);
    $compliance1->assignRole('compliance');

    $compliance2 = User::factory()->create(['company_id' => $this->company->id]);
    $compliance2->industries()->attach($this->industry);
    $compliance2->assignRole('compliance');

    // Wrong company — must not be notified even with a matching role and industry.
    $otherCompany = Company::factory()->create();
    $outsider = User::factory()->create(['company_id' => $otherCompany->id]);
    $outsider->industries()->attach($this->industry);
    $outsider->assignRole('compliance');

    // Same company, wrong industry — must not be notified either.
    $otherIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    $wrongIndustry = User::factory()->create(['company_id' => $this->company->id]);
    $wrongIndustry->industries()->attach($otherIndustry);
    $wrongIndustry->assignRole('compliance');

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => null,
        'notes' => 'Needs a follow up call',
    ]);

    Action::factory()->roleBased('compliance')->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
        'todo_name' => 'Review client compliance',
    ]);

    CheckActions::run($client);

    expect(TodoItem::where('name', 'Review client compliance')->pluck('user_id')->sort()->values()->all())
        ->toBe([$compliance1->id, $compliance2->id]);
});

test('a role-based action creates a todo for every user with that role for a candidate reference too', function () {
    $this->seed(RoleSeeder::class);

    $compliance1 = User::factory()->create(['company_id' => $this->company->id]);
    $compliance1->industries()->attach($this->industry);
    $compliance1->assignRole('compliance');

    $compliance2 = User::factory()->create(['company_id' => $this->company->id]);
    $compliance2->industries()->attach($this->industry);
    $compliance2->assignRole('compliance');

    // The reference's own candidate has no consultant at all — proving this
    // doesn't matter for role-based assignment, unlike the consultant-based
    // path which falls back to the candidate's consultant.
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => null,
    ]);

    $reference = $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'status' => 'rejected',
    ]);

    Action::factory()->roleBased('compliance')->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => CandidateReference::class,
        'conditions' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'rejected'],
        ],
        'todo_name' => 'Review rejected reference',
    ]);

    CheckActions::run($reference);

    expect(TodoItem::where('name', 'Review rejected reference')->pluck('user_id')->sort()->values()->all())
        ->toBe([$compliance1->id, $compliance2->id]);
});

test('a role-based action with nobody in the matching role does not create a trigger, leaving it free to fire once someone is added', function () {
    $this->seed(RoleSeeder::class);

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => null,
        'notes' => 'Needs a follow up call',
    ]);

    $action = Action::factory()->roleBased('compliance')->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    expect(TodoItem::count())->toBe(0)
        ->and($action->openTriggerFor($client))->toBeNull();

    $compliance = User::factory()->create(['company_id' => $this->company->id]);
    $compliance->industries()->attach($this->industry);
    $compliance->assignRole('compliance');

    CheckActions::run($client);

    expect(TodoItem::where('user_id', $compliance->id)->exists())->toBeTrue();
});

test('resolving a role-based trigger completes every todo it created', function () {
    $this->seed(RoleSeeder::class);

    $compliance1 = User::factory()->create(['company_id' => $this->company->id]);
    $compliance1->industries()->attach($this->industry);
    $compliance1->assignRole('compliance');

    $compliance2 = User::factory()->create(['company_id' => $this->company->id]);
    $compliance2->industries()->attach($this->industry);
    $compliance2->assignRole('compliance');

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => null,
        'notes' => 'Needs a follow up call',
    ]);

    $action = Action::factory()->roleBased('compliance')->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    $trigger = $action->openTriggerFor($client);
    expect($trigger->todoItems)->toHaveCount(2);

    $client->update(['notes' => null]);
    CheckActions::run($client);

    expect($trigger->refresh()->isOpen())->toBeFalse();
    expect($trigger->todoItems->every(fn (TodoItem $todoItem) => $todoItem->refresh()->isComplete()))->toBeTrue();
});

function makeActionEmailTemplate(string $companyId, string $industryId, string $audience): EmailTemplate
{
    return EmailTemplate::create([
        'company_id' => $companyId,
        'industry_id' => $industryId,
        'name' => 'Action template',
        'type' => EmailTemplateType::Custom->value,
        'audience' => $audience,
        'subject' => 'Hello',
        'body' => 'Body',
    ]);
}

test('a client action with an email template dispatches it to the client', function () {
    Bus::fake();

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'client');

    Action::factory()->withEmailTemplate($template)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->template->is($template) && $job->recipient->is($client));
});

test('a candidate action with an email template dispatches it to the candidate', function () {
    Bus::fake();

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
        'first_name' => 'Jane',
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'candidate');

    Action::factory()->withEmailTemplate($template)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => EducationCandidate::class,
        'conditions' => [
            ['field' => 'first_name', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($candidate);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->template->is($template) && $job->recipient->is($candidate));
});

test('a booking action configured to email the client dispatches it to the bookings client', function () {
    Bus::fake();

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'client');

    Action::factory()->withEmailTemplate($template, ActionEmailRecipient::Client)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Booking::class,
        'conditions' => [
            ['field' => 'status', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($booking);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($booking->client));
});

test('a booking action configured to email the candidate dispatches it to the bookings candidate', function () {
    Bus::fake();

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'candidate');

    Action::factory()->withEmailTemplate($template, ActionEmailRecipient::Candidate)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Booking::class,
        'conditions' => [
            ['field' => 'status', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($booking);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($booking->candidate));
});

test('a vacancy action with an email template dispatches it to the vacancys client', function () {
    Bus::fake();

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'consultant_id' => $this->consultant->id,
        'title' => 'Year 3 Class Teacher',
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'client');

    Action::factory()->withEmailTemplate($template)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Vacancy::class,
        'conditions' => [
            ['field' => 'title', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($vacancy);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->template->is($template) && $job->recipient->is($client));
});

test('a candidate reference action with an email template dispatches it to the references candidate', function () {
    Bus::fake();

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->consultant->id,
    ]);

    $reference = $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'status' => 'rejected',
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'candidate');

    Action::factory()->withEmailTemplate($template)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => CandidateReference::class,
        'conditions' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'rejected'],
        ],
    ]);

    CheckActions::run($reference);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->template->is($template) && $job->recipient->is($candidate));
});

test('an action with no email template configured does not dispatch an email', function () {
    Bus::fake();

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

    Bus::assertNotDispatched(SendCustomTemplateEmail::class);
});

test('the email is not re-dispatched on a second run while the trigger stays open', function () {
    Bus::fake();

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'client');

    Action::factory()->withEmailTemplate($template)->create([
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

    Bus::assertDispatched(SendCustomTemplateEmail::class, 1);
});

test('the email still sends even when a role-based todo has nobody in the matching role, since they are independent', function () {
    $this->seed(RoleSeeder::class);
    Bus::fake();

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => null,
        'notes' => 'Needs a follow up call',
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'client');

    Action::factory()->roleBased('compliance')->withEmailTemplate($template)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
    ]);

    CheckActions::run($client);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($client));
    expect(TodoItem::count())->toBe(0);
});

test('an email-only action (no todo name) sends the email without creating any todo', function () {
    Bus::fake();

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
        'notes' => 'Needs a follow up call',
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'client');

    Action::factory()->withEmailTemplate($template)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
        'todo_name' => null,
        'todo_description' => null,
    ]);

    CheckActions::run($client);

    Bus::assertDispatched(SendCustomTemplateEmail::class, fn (SendCustomTemplateEmail $job): bool => $job->recipient->is($client));
    expect(TodoItem::count())->toBe(0);
});

test('an email-only action still fires even when the record has no consultant, since no todo assignee is needed', function () {
    Bus::fake();

    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => null,
        'notes' => 'Needs a follow up call',
    ]);

    $template = makeActionEmailTemplate($this->company->id, $this->industry->id, 'client');

    Action::factory()->withEmailTemplate($template)->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'notes', 'operator' => 'filled'],
        ],
        'todo_name' => null,
    ]);

    CheckActions::run($client);

    Bus::assertDispatched(SendCustomTemplateEmail::class);
});

test('an action with neither a todo name nor an email template configured never fires', function () {
    Bus::fake();

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
        'todo_name' => null,
    ]);

    CheckActions::run($client);

    expect(TodoItem::count())->toBe(0)
        ->and($action->openTriggerFor($client))->toBeNull();
    Bus::assertNotDispatched(SendCustomTemplateEmail::class);
});

test('the user observer re-checks actions for a candidate when their user account is created', function () {
    // A candidate's own record is never re-saved when their application
    // completes — the User account created at that point (see
    // completeApplication()) is the only signal UserObserver gets, so it
    // has to re-run CheckActions itself rather than relying on
    // EducationCandidateObserver's own saved() hook.
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

    expect(TodoItem::where('model_type', EducationCandidate::class)->where('model_id', $candidate->id)->exists())->toBeFalse();

    User::factory()->create([
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $todo = TodoItem::where('model_type', EducationCandidate::class)->where('model_id', $candidate->id)->first();

    expect($todo)->not->toBeNull()
        ->and($todo->name)->toBe('Chase candidate documents')
        ->and($todo->user_id)->toBe($this->consultant->id);
});
