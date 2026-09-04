<?php

use App\Filament\Resources\ReferenceForms\Pages\CreateReferenceForm;
use App\Filament\Resources\ReferenceForms\Pages\EditReferenceForm;
use App\Filament\Resources\ReferenceForms\Pages\ListReferenceForms;
use App\Filament\Resources\ReferenceForms\ReferenceFormResource;
use App\Models\Company;
use App\Models\Industry;
use App\Models\ReferenceForm;
use App\Models\ReferenceFormField;
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

test('list page renders', function () {
    Livewire::test(ListReferenceForms::class)
        ->assertSuccessful();
});

test('non-admin cannot access the reference forms resource', function () {
    $consultant = User::factory()->create(['company_id' => $this->company->id]);
    $consultant->industries()->attach($this->industry);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(ReferenceFormResource::canViewAny())->toBeFalse();
    $this->get('/crm/reference-forms')->assertRedirect('/crm');
});

test('compliance can access the reference forms resource', function () {
    $compliance = User::factory()->create(['company_id' => $this->company->id]);
    $compliance->industries()->attach($this->industry);
    $compliance->assignRole('compliance');
    $this->actingAs($compliance);

    expect(ReferenceFormResource::canViewAny())->toBeTrue();
    Livewire::test(ListReferenceForms::class)->assertSuccessful();
});

test('list only shows reference forms for the active company and industry', function () {
    $ownForm = ReferenceForm::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $otherForm = ReferenceForm::factory()->create();

    Livewire::test(ListReferenceForms::class)
        ->assertCanSeeTableRecords([$ownForm])
        ->assertCanNotSeeTableRecords([$otherForm]);
});

test('can create a reference form, and industry_id is stamped from the active industry', function () {
    Livewire::test(CreateReferenceForm::class)
        ->fillForm([
            'name' => 'Professional',
            'description' => 'For a former employer.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $form = ReferenceForm::where('name', 'Professional')->first();

    expect($form)->not->toBeNull()
        ->and($form->company_id)->toBe($this->company->id)
        ->and($form->industry_id)->toBe($this->industry->id);
});

test('name is required', function () {
    Livewire::test(CreateReferenceForm::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name']);
});

test('questions added via the repeater get an auto-slugged key, deduplicated on collision', function () {
    $form = ReferenceForm::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditReferenceForm::class, ['record' => $form->getRouteKey()])
        ->fillForm([
            'fields' => [
                'field-1' => ['label' => 'Worked From', 'field_type' => 'date', 'required' => true],
                'field-2' => ['label' => 'Worked From', 'field_type' => 'text', 'required' => true],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $form->refresh();

    expect($form->fields)->toHaveCount(2);
    expect($form->fields->pluck('key')->sort()->values()->all())->toBe(['worked_from', 'worked_from_2']);
});

test('a radio question stores its choices and a show_when dependency', function () {
    $form = ReferenceForm::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditReferenceForm::class, ['record' => $form->getRouteKey()])
        ->fillForm([
            'fields' => [
                'field-1' => [
                    'label' => 'Any safeguarding concerns?',
                    'field_type' => 'radio',
                    'options' => ['Yes', 'No'],
                    'required' => true,
                ],
                'field-2' => [
                    'label' => 'Please provide details',
                    'field_type' => 'textarea',
                    'required' => true,
                    'show_when_field_key' => 'any_safeguarding_concerns',
                    'show_when_value' => 'Yes',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $form->refresh();

    $radioField = $form->fields->firstWhere('label', 'Any safeguarding concerns?');
    $detailsField = $form->fields->firstWhere('label', 'Please provide details');

    expect($radioField->options)->toBe(['Yes', 'No']);
    expect($detailsField->show_when_field_key)->toBe('any_safeguarding_concerns');
    expect($detailsField->show_when_value)->toBe('Yes');
});

test('can edit and delete a reference form', function () {
    $form = ReferenceForm::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Old Name',
    ]);
    ReferenceFormField::factory()->create(['reference_form_id' => $form->id]);

    Livewire::test(EditReferenceForm::class, ['record' => $form->getRouteKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($form->fresh()->name)->toBe('New Name');

    Livewire::test(EditReferenceForm::class, ['record' => $form->getRouteKey()])
        ->callAction('delete');

    expect(ReferenceForm::find($form->id))->toBeNull();
});
