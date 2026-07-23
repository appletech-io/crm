<?php

use App\Filament\Resources\Actions\Pages\CreateAction;
use App\Filament\Resources\Actions\Pages\EditAction;
use App\Filament\Resources\Actions\Pages\ListActions;
use App\Models\Action;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->industries()->attach($this->industry);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('list page renders for admins', function () {
    Livewire::test(ListActions::class)->assertSuccessful();
});

test('consultants cannot access the actions resource', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);

    $this->get('/crm/actions')->assertRedirect('/crm');
});

test('site_admin can access the actions resource', function () {
    $siteAdmin = User::factory()->create(['company_id' => $this->company->id]);
    $siteAdmin->industries()->attach($this->industry);
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    Cache::put("user.{$siteAdmin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$siteAdmin->id}.active_industry_id", $this->industry->id);

    Livewire::test(ListActions::class)->assertSuccessful();
});

test('can create an action targeting a client with a valid condition', function () {
    Livewire::test(CreateAction::class)
        ->fillForm([
            'name' => 'Chase client notes',
            'model_type' => Client::class,
            'conditions' => [
                'item-1' => ['field' => 'name', 'operator' => 'filled'],
            ],
            'todo_name' => 'Follow up with client',
            'todo_description' => 'Client has notes that need chasing.',
            'todo_priority' => 'high',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $action = Action::where('name', 'Chase client notes')->first();

    expect($action)->not->toBeNull()
        ->and($action->company_id)->toBe($this->company->id)
        ->and($action->industry_id)->toBe($this->industry->id)
        ->and($action->model_type)->toBe(Client::class)
        ->and($action->conditions)->toBe([['field' => 'name', 'operator' => 'filled']])
        ->and($action->todo_name)->toBe('Follow up with client')
        ->and($action->todo_priority->value)->toBe('high')
        ->and($action->is_active)->toBeTrue();
});

test('a condition field valid for one model type is rejected for another', function () {
    // "name" is a real Client field, but not a valid field for Booking.
    Livewire::test(CreateAction::class)
        ->fillForm([
            'name' => 'Invalid booking condition',
            'model_type' => Booking::class,
            'conditions' => [
                'item-1' => ['field' => 'name', 'operator' => 'filled'],
            ],
            'todo_name' => 'x',
            'todo_priority' => 'medium',
        ])
        ->call('create')
        ->assertHasFormErrors(['conditions.item-1.field']);
});

test('a condition field valid for the selected model type is accepted', function () {
    // "status" is a real Booking field.
    Livewire::test(CreateAction::class)
        ->fillForm([
            'name' => 'Valid booking condition',
            'model_type' => Booking::class,
            'conditions' => [
                'item-1' => ['field' => 'status', 'operator' => 'filled'],
            ],
            'todo_name' => 'x',
            'todo_priority' => 'medium',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $action = Action::where('name', 'Valid booking condition')->first();

    expect($action->conditions)->toBe([['field' => 'status', 'operator' => 'filled']]);
});

test('model type options include client, booking and the active industrys candidate model only', function () {
    Livewire::test(CreateAction::class)
        ->assertFormFieldExists('model_type', function ($field): bool {
            $options = $field->getOptions();

            return array_key_exists(Client::class, $options)
                && array_key_exists(Booking::class, $options)
                && array_key_exists(EducationCandidate::class, $options)
                && ! array_key_exists(HealthcareCandidate::class, $options);
        });
});

test('edit page renders and loads the existing conditions', function () {
    $action = Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'name', 'operator' => 'filled'],
        ],
    ]);

    Livewire::test(EditAction::class, ['record' => $action->getRouteKey()])
        ->assertSuccessful()
        ->assertFormSet([
            'model_type' => Client::class,
        ]);
});

test('updating an action re-validates conditions against its model type', function () {
    $action = Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
        'conditions' => [
            ['field' => 'name', 'operator' => 'filled'],
        ],
    ]);

    $component = Livewire::test(EditAction::class, ['record' => $action->getRouteKey()]);

    // Editing replaces the content of the already-loaded item, keyed by
    // whatever item key the repeater assigned when it hydrated the record.
    $itemKey = array_key_first($component->get('data.conditions'));

    $component
        ->set("data.conditions.{$itemKey}.field", 'phone')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($action->refresh()->conditions)->toBe([['field' => 'phone', 'operator' => 'filled']]);
});

test('actions are scoped to the current company and industry', function () {
    $mine = Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
    ]);

    $otherCompany = Company::factory()->create();
    $theirs = Action::factory()->create([
        'company_id' => $otherCompany->id,
        'industry_id' => $this->industry->id,
        'model_type' => Client::class,
    ]);

    $otherIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    $otherIndustryAction = Action::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $otherIndustry->id,
        'model_type' => Client::class,
    ]);

    Livewire::test(ListActions::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs, $otherIndustryAction]);
});
