<?php

use App\Filament\Resources\TodoItems\Pages\CreateTodoItem;
use App\Filament\Resources\TodoItems\Pages\ListTodoItems;
use App\Filament\Resources\TodoItems\Schemas\TodoItemForm;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\TodoItem;
use App\Models\User;
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

    Livewire::test(ListTodoItems::class)
        ->callTableAction('toggleComplete', $todoItem);

    expect($todoItem->refresh()->isComplete())->toBeFalse();
});
