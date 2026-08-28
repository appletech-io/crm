<?php

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Resources\TodoItems\Pages\CreateTodoItem;
use App\Filament\Resources\TodoItems\Pages\EditTodoItem;
use App\Filament\Resources\TodoItems\Pages\ListTodoItems;
use App\Filament\Resources\TodoItems\Schemas\TodoItemForm;
use App\Filament\Resources\Vacancies\VacancyResource;
use App\Filament\Support\TodoLinkedRecord;
use App\Models\Booking;
use App\Models\CandidateReference;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\TodoItem;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('consultant');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('list page renders', function () {
    Livewire::test(ListTodoItems::class)->assertSuccessful();
});

test('site_admin cannot access the todo items resource', function () {
    $siteAdmin = User::factory()->create(['company_id' => $this->company->id]);
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    $this->get('/crm/todo-items')->assertRedirect('/crm');
});

test('can create a todo item and it is owned by the current user', function () {
    Livewire::test(CreateTodoItem::class)
        ->fillForm([
            'name' => 'Chase reference for candidate',
            'priority' => 'high',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $todoItem = TodoItem::where('name', 'Chase reference for candidate')->first();

    expect($todoItem)->not->toBeNull()
        ->and($todoItem->user_id)->toBe($this->user->id)
        ->and($todoItem->priority->value)->toBe('high');
});

test('a todo item can be created with a description', function () {
    Livewire::test(CreateTodoItem::class)
        ->fillForm([
            'name' => 'Chase reference for candidate',
            'description' => 'Call the second referee, the first one already replied.',
            'priority' => 'medium',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $todoItem = TodoItem::where('name', 'Chase reference for candidate')->first();

    expect($todoItem->description)->toBe('Call the second referee, the first one already replied.');
});

test('the todo item name cannot exceed the maximum length', function () {
    Livewire::test(CreateTodoItem::class)
        ->fillForm([
            'name' => str_repeat('a', TodoItemForm::NAME_MAX_LENGTH + 1),
            'priority' => 'medium',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'max']);
});

test('a todo item can be linked to a client', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(CreateTodoItem::class)
        ->fillForm([
            'name' => 'Follow up with client',
            'priority' => 'medium',
            'model_type' => Client::class,
            'model_id' => $client->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $todoItem = TodoItem::where('name', 'Follow up with client')->first();

    expect($todoItem->model_type)->toBe(Client::class)
        ->and($todoItem->model_id)->toBe($client->id)
        ->and($todoItem->linkedRecordLabel())->toBe($client->name);
});

test('a todo item can be linked to a candidate', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->user->id,
    ]);

    Livewire::test(CreateTodoItem::class)
        ->fillForm([
            'name' => 'Chase compliance documents',
            'priority' => 'medium',
            'model_type' => EducationCandidate::class,
            'model_id' => $candidate->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $todoItem = TodoItem::where('name', 'Chase compliance documents')->first();

    expect($todoItem->model_type)->toBe(EducationCandidate::class)
        ->and($todoItem->model_id)->toBe($candidate->id);
});

test('the link to type options only include the active industrys candidate model', function () {
    Livewire::test(CreateTodoItem::class)
        ->assertFormFieldExists('model_type', function (Select $field): bool {
            $options = $field->getOptions();

            return array_key_exists(Client::class, $options)
                && array_key_exists(EducationCandidate::class, $options)
                && array_key_exists(Booking::class, $options)
                && ! array_key_exists(HealthcareCandidate::class, $options);
        });
});

test('client link options are scoped to the current company and sector', function () {
    $ownClient = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $otherCompany = Company::factory()->create();
    $otherCompanyClient = Client::factory()->create([
        'company_id' => $otherCompany->id,
        'industry_id' => $this->industry->id,
    ]);

    $otherIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    $otherIndustryClient = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $otherIndustry->id,
    ]);

    Livewire::test(CreateTodoItem::class)
        ->set('data.model_type', Client::class)
        ->assertFormFieldExists('model_id', function (Select $field) use ($ownClient, $otherCompanyClient, $otherIndustryClient): bool {
            $options = $field->getOptions();

            return array_key_exists($ownClient->id, $options)
                && ! array_key_exists($otherCompanyClient->id, $options)
                && ! array_key_exists($otherIndustryClient->id, $options);
        });
});

test('candidate link options are scoped to the current company and consultant', function () {
    $ownCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $this->user->id,
    ]);

    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);
    $otherConsultant->assignRole('consultant');
    $otherConsultantCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $otherConsultant->id,
    ]);

    $otherCompany = Company::factory()->create();
    $otherCompanyCandidate = EducationCandidate::factory()->create([
        'company_id' => $otherCompany->id,
        'consultant_id' => $this->user->id,
    ]);

    Livewire::test(CreateTodoItem::class)
        ->set('data.model_type', EducationCandidate::class)
        ->assertFormFieldExists('model_id', function (Select $field) use ($ownCandidate, $otherConsultantCandidate, $otherCompanyCandidate): bool {
            $options = $field->getOptions();

            return array_key_exists($ownCandidate->id, $options)
                && ! array_key_exists($otherConsultantCandidate->id, $options)
                && ! array_key_exists($otherCompanyCandidate->id, $options);
        });
});

test('admin users see all candidates as link options regardless of consultant', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->industries()->attach($this->industry);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Cache::put("user.{$admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$admin->id}.active_industry_id", $this->industry->id);

    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);
    $otherConsultant->assignRole('consultant');
    $theirCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $otherConsultant->id,
    ]);

    Livewire::test(CreateTodoItem::class)
        ->set('data.model_type', EducationCandidate::class)
        ->assertFormFieldExists('model_id', function (Select $field) use ($theirCandidate): bool {
            return array_key_exists($theirCandidate->id, $field->getOptions());
        });
});

test('compliance users see all candidates as link options regardless of consultant', function () {
    $compliance = User::factory()->create(['company_id' => $this->company->id]);
    $compliance->industries()->attach($this->industry);
    $compliance->assignRole('compliance');
    $this->actingAs($compliance);

    Cache::put("user.{$compliance->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$compliance->id}.active_industry_id", $this->industry->id);

    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);
    $otherConsultant->assignRole('consultant');
    $theirCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $otherConsultant->id,
    ]);

    Livewire::test(CreateTodoItem::class)
        ->set('data.model_type', EducationCandidate::class)
        ->assertFormFieldExists('model_id', function (Select $field) use ($theirCandidate): bool {
            return array_key_exists($theirCandidate->id, $field->getOptions());
        });
});

test('compliance users can open a todo item linked to another consultants candidate without it falling back to a bare id', function () {
    $compliance = User::factory()->create(['company_id' => $this->company->id]);
    $compliance->industries()->attach($this->industry);
    $compliance->assignRole('compliance');
    $this->actingAs($compliance);

    Cache::put("user.{$compliance->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$compliance->id}.active_industry_id", $this->industry->id);

    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);
    $otherConsultant->assignRole('consultant');
    $theirCandidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'consultant_id' => $otherConsultant->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $todoItem = TodoItem::factory()->create([
        'user_id' => $compliance->id,
        'model_type' => EducationCandidate::class,
        'model_id' => $theirCandidate->id,
    ]);

    Livewire::test(EditTodoItem::class, ['record' => $todoItem->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldExists('model_id', function (Select $field) use ($theirCandidate): bool {
            return array_key_exists($theirCandidate->id, $field->getOptions());
        });
});

test('booking link options are scoped to the current company, sector and consultant', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $ownBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'consultant_id' => $this->user->id,
    ]);

    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);
    $otherConsultant->assignRole('consultant');
    $otherConsultantBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'consultant_id' => $otherConsultant->id,
    ]);

    $otherCompany = Company::factory()->create();
    $otherCompanyBooking = Booking::factory()->create([
        'company_id' => $otherCompany->id,
        'candidate_type' => EducationCandidate::class,
        'consultant_id' => $this->user->id,
    ]);

    $healthcareCandidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
    $otherSectorBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'candidate_id' => $healthcareCandidate->id,
        'candidate_type' => HealthcareCandidate::class,
        'consultant_id' => $this->user->id,
    ]);

    Livewire::test(CreateTodoItem::class)
        ->set('data.model_type', Booking::class)
        ->assertFormFieldExists('model_id', function (Select $field) use ($ownBooking, $otherConsultantBooking, $otherCompanyBooking, $otherSectorBooking): bool {
            $options = $field->getOptions();

            return array_key_exists($ownBooking->id, $options)
                && ! array_key_exists($otherConsultantBooking->id, $options)
                && ! array_key_exists($otherCompanyBooking->id, $options)
                && ! array_key_exists($otherSectorBooking->id, $options);
        });
});

test('users only see their own todo items', function () {
    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    $otherUser->assignRole('consultant');

    $mine = TodoItem::factory()->create(['user_id' => $this->user->id, 'name' => 'My task']);
    $theirs = TodoItem::factory()->create(['user_id' => $otherUser->id, 'name' => 'Their task']);

    Livewire::test(ListTodoItems::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

test('cannot view or edit another users todo item', function () {
    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    $otherUser->assignRole('consultant');

    $theirs = TodoItem::factory()->create(['user_id' => $otherUser->id]);

    $this->get("/crm/todo-items/{$theirs->id}/edit")->assertNotFound();
});

test('a todo item can be marked complete and reopened', function () {
    $todoItem = TodoItem::factory()->create(['user_id' => $this->user->id]);

    expect($todoItem->isComplete())->toBeFalse();

    Livewire::test(ListTodoItems::class)
        ->callTableAction('toggleComplete', $todoItem);

    expect($todoItem->refresh()->isComplete())->toBeTrue();

    // The table defaults to hiding completed items (see below), so a fresh
    // component needs the "Completed" filter applied to still find this
    // now-completed record before it can be reopened.
    Livewire::test(ListTodoItems::class)
        ->filterTable('completed_at', true)
        ->callTableAction('toggleComplete', $todoItem);

    expect($todoItem->refresh()->isComplete())->toBeFalse();
});

test('the list only shows outstanding todo items by default', function () {
    $outstanding = TodoItem::factory()->create(['user_id' => $this->user->id, 'completed_at' => null]);
    $completed = TodoItem::factory()->create(['user_id' => $this->user->id, 'completed_at' => now()]);

    Livewire::test(ListTodoItems::class)
        ->assertCanSeeTableRecords([$outstanding])
        ->assertCanNotSeeTableRecords([$completed]);
});

test('filtering to completed shows completed todo items and hides outstanding ones', function () {
    $outstanding = TodoItem::factory()->create(['user_id' => $this->user->id, 'completed_at' => null]);
    $completed = TodoItem::factory()->create(['user_id' => $this->user->id, 'completed_at' => now()]);

    Livewire::test(ListTodoItems::class)
        ->filterTable('completed_at', true)
        ->assertCanSeeTableRecords([$completed])
        ->assertCanNotSeeTableRecords([$outstanding]);
});

test('there is no delete action on the todo list or its rows', function () {
    $todoItem = TodoItem::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(ListTodoItems::class)
        ->assertTableBulkActionDoesNotExist('delete')
        ->assertTableActionDoesNotExist('delete');

    expect($todoItem->exists)->toBeTrue();
});

test('there is no delete action on the todo edit page', function () {
    $todoItem = TodoItem::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(EditTodoItem::class, ['record' => $todoItem->getRouteKey()])
        ->assertActionDoesNotExist('delete');
});

test('the link to field is editable on create but read-only on edit', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(CreateTodoItem::class)
        ->assertFormFieldIsEnabled('model_type');

    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'model_type' => Client::class,
        'model_id' => $client->id,
    ]);

    Livewire::test(EditTodoItem::class, ['record' => $todoItem->getRouteKey()])
        ->assertFormFieldIsDisabled('model_type');
});

test('linked record label and link for a client and a candidate', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $clientTodo = TodoItem::factory()->create(['user_id' => $this->user->id, 'model_type' => Client::class, 'model_id' => $client->id]);
    $candidateTodo = TodoItem::factory()->create(['user_id' => $this->user->id, 'model_type' => EducationCandidate::class, 'model_id' => $candidate->id]);

    expect($clientTodo->linkedRecordLabel())->toBe($client->name)
        ->and(TodoLinkedRecord::links($clientTodo->model))->toBe([
            ['label' => $client->name, 'url' => ClientResource::getUrl('edit', ['record' => $client])],
        ])
        ->and($candidateTodo->linkedRecordLabel())->toBe(trim("{$candidate->first_name} {$candidate->last_name}"))
        ->and(TodoLinkedRecord::links($candidateTodo->model))->toBe([
            ['label' => trim("{$candidate->first_name} {$candidate->last_name}"), 'url' => EducationCandidateResource::getUrl('edit', ['record' => $candidate])],
        ]);
});

test('a todo item linked to a booking links to the booking itself, its candidate and its client', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'consultant_id' => $this->user->id,
    ]);

    $todoItem = TodoItem::factory()->create(['user_id' => $this->user->id, 'model_type' => Booking::class, 'model_id' => $booking->id]);

    expect($todoItem->linkedRecordLabel())->toBe("Booking #{$booking->id}")
        ->and(TodoLinkedRecord::links($todoItem->model))->toBe([
            ['label' => "Booking #{$booking->id}", 'url' => BookingResource::getUrl('edit', ['record' => $booking])],
            ['label' => trim("{$candidate->first_name} {$candidate->last_name}"), 'url' => EducationCandidateResource::getUrl('edit', ['record' => $candidate])],
            ['label' => $client->name, 'url' => ClientResource::getUrl('edit', ['record' => $client])],
        ]);
});

test('a todo item linked to a vacancy links to its filled candidate, the vacancy itself and its client', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'filled_by_id' => $candidate->id,
        'filled_by_type' => EducationCandidate::class,
    ]);

    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'model_type' => Vacancy::class,
        'model_id' => $vacancy->id,
    ]);

    expect($todoItem->linkedRecordLabel())->toBe($vacancy->title)
        ->and(TodoLinkedRecord::links($todoItem->model))->toBe([
            ['label' => trim("{$candidate->first_name} {$candidate->last_name}"), 'url' => EducationCandidateResource::getUrl('edit', ['record' => $candidate])],
            ['label' => $vacancy->title, 'url' => VacancyResource::getUrl('edit', ['record' => $vacancy])],
            ['label' => $client->name, 'url' => ClientResource::getUrl('edit', ['record' => $client])],
        ]);
});

test('an unfilled vacancy only links to the vacancy itself and its client', function () {
    $client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);
    $vacancy = Vacancy::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
    ]);

    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'model_type' => Vacancy::class,
        'model_id' => $vacancy->id,
    ]);

    expect(TodoLinkedRecord::links($todoItem->model))->toBe([
        ['label' => $vacancy->title, 'url' => VacancyResource::getUrl('edit', ['record' => $vacancy])],
        ['label' => $client->name, 'url' => ClientResource::getUrl('edit', ['record' => $client])],
    ]);
});

test('a todo item linked to a reference shows the candidate, not the referee, and links to the candidate', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $reference = CandidateReference::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'type' => 'professional',
        'first_name' => 'Referee',
        'last_name' => 'Smith',
    ]);

    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'model_type' => CandidateReference::class,
        'model_id' => $reference->id,
    ]);

    expect($todoItem->linkedRecordLabel())->toBe('Reference: Jane Doe')
        ->and(TodoLinkedRecord::links($todoItem->model))->toBe([
            ['label' => 'Jane Doe', 'url' => EducationCandidateResource::getUrl('edit', ['record' => $candidate])],
        ]);
});

test('a todo item linked to an education application links to the candidate', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
    $application = EducationApplication::factory()->create(['education_candidate_id' => $candidate->id]);

    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'model_type' => EducationApplication::class,
        'model_id' => $application->id,
    ]);

    expect($todoItem->linkedRecordLabel())->toBe('Application: Jane Doe')
        ->and(TodoLinkedRecord::links($todoItem->model))->toBe([
            ['label' => 'Jane Doe', 'url' => EducationCandidateResource::getUrl('edit', ['record' => $candidate])],
        ]);
});

test('a todo item linked to a healthcare application links to the candidate', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'John',
        'last_name' => 'Smith',
    ]);
    $application = HealthcareApplication::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'token' => 'test-token',
    ]);

    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'model_type' => HealthcareApplication::class,
        'model_id' => $application->id,
    ]);

    expect($todoItem->linkedRecordLabel())->toBe('Application: John Smith')
        ->and(TodoLinkedRecord::links($todoItem->model))->toBe([
            ['label' => 'John Smith', 'url' => HealthcareCandidateResource::getUrl('edit', ['record' => $candidate])],
        ]);
});

test('the link to field is hidden on edit when linked to a reference, but the record link is shown instead', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $reference = CandidateReference::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'type' => 'professional',
        'first_name' => 'Referee',
        'last_name' => 'Smith',
    ]);

    $todoItem = TodoItem::factory()->create([
        'user_id' => $this->user->id,
        'model_type' => CandidateReference::class,
        'model_id' => $reference->id,
    ]);

    Livewire::test(EditTodoItem::class, ['record' => $todoItem->getRouteKey()])
        ->assertFormFieldIsHidden('model_type')
        ->assertSee(trim("{$candidate->first_name} {$candidate->last_name}"));
});
