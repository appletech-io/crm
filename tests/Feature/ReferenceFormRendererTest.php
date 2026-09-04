<?php

use App\Enums\ReferenceType;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\ReferenceForm;
use App\Models\ReferenceFormField;
use App\Services\References\ReferenceFormRenderer;

test('snapshotFor groups consecutive same-heading fields into sections and substitutes the company name placeholder', function () {
    $form = ReferenceForm::factory()->create();

    ReferenceFormField::factory()->create([
        'reference_form_id' => $form->id, 'label' => 'Worked From', 'field_type' => 'date', 'sort_order' => 0,
    ]);
    ReferenceFormField::factory()->create([
        'reference_form_id' => $form->id, 'label' => 'Please tell :company_name about any concerns', 'field_type' => 'radio',
        'options' => ['Yes', 'No'], 'sort_order' => 1,
    ]);
    ReferenceFormField::factory()->create([
        'reference_form_id' => $form->id, 'label' => 'Punctuality', 'field_type' => 'radio', 'options' => ['Good', 'Poor'],
        'section_heading' => 'Ratings', 'sort_order' => 2,
    ]);
    ReferenceFormField::factory()->create([
        'reference_form_id' => $form->id, 'label' => 'Timekeeping', 'field_type' => 'radio', 'options' => ['Good', 'Poor'],
        'section_heading' => 'Ratings', 'sort_order' => 3,
    ]);

    $sections = ReferenceFormRenderer::snapshotFor($form->fresh(), 'Acme Recruitment');

    expect($sections)->toHaveCount(2);
    expect($sections[0]['heading'])->toBeNull();
    expect($sections[0]['fields'])->toHaveCount(2);
    expect($sections[0]['fields'][1]['label'])->toBe('Please tell Acme Recruitment about any concerns');
    expect($sections[1]['heading'])->toBe('Ratings');
    expect($sections[1]['fields'])->toHaveCount(2);
});

test('snapshotFor converts a radio field\'s plain choice list into value => label pairs, matching the referee form\'s expected shape', function () {
    $form = ReferenceForm::factory()->create();

    ReferenceFormField::factory()->create([
        'reference_form_id' => $form->id, 'label' => 'Suitable?', 'field_type' => 'radio', 'options' => ['Yes', 'No', 'N/A'],
    ]);

    $sections = ReferenceFormRenderer::snapshotFor($form->fresh(), 'Acme');
    $field = $sections[0]['fields'][0];

    expect($field['type'])->toBe('radio');
    expect($field['options'])->toBe(['Yes' => 'Yes', 'No' => 'No', 'N/A' => 'N/A']);
});

test('snapshotFor carries a show_when dependency as a [key, value] pair', function () {
    $form = ReferenceForm::factory()->create();

    ReferenceFormField::factory()->create([
        'reference_form_id' => $form->id, 'key' => 'suitable', 'label' => 'Suitable?', 'field_type' => 'radio',
        'options' => ['Yes', 'No'], 'sort_order' => 0,
    ]);
    ReferenceFormField::factory()->create([
        'reference_form_id' => $form->id, 'label' => 'Why not?', 'field_type' => 'textarea',
        'show_when_field_key' => 'suitable', 'show_when_value' => 'No', 'sort_order' => 1,
    ]);

    $sections = ReferenceFormRenderer::snapshotFor($form->fresh(), 'Acme');
    $field = $sections[0]['fields'][1];

    expect($field['show_when'])->toBe(['suitable', 'No']);
});

test('rulesFor and attributeNamesFor derive from a candidate reference\'s stored schema snapshot', function () {
    $form = ReferenceForm::factory()->create();
    ReferenceFormField::factory()->create([
        'reference_form_id' => $form->id, 'key' => 'worked_from', 'label' => 'Worked From', 'field_type' => 'date', 'required' => true,
    ]);
    ReferenceFormField::factory()->create([
        'reference_form_id' => $form->id, 'key' => 'suitable', 'label' => 'Suitable?', 'field_type' => 'radio',
        'options' => ['Yes', 'No'], 'required' => true,
    ]);

    $candidate = EducationCandidate::factory()->create(['company_id' => Company::factory()->create()->id]);
    $reference = $candidate->references()->create([
        'reference_form_id' => $form->id,
        'schema' => ReferenceFormRenderer::snapshotFor($form->fresh(), 'Acme'),
    ]);

    $rules = ReferenceFormRenderer::rulesFor($reference);
    expect($rules['answers.worked_from'])->toContain('required')->toContain('date');
    expect($rules['answers.suitable'])->toContain('in:Yes,No');

    $names = ReferenceFormRenderer::attributeNamesFor($reference, 'Acme');
    expect($names['answers.worked_from'])->toBe('Worked From');
});

test('sectionsFor falls back to the legacy ReferenceFormSchema when a reference has no stored schema', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => Company::factory()->create()->id]);
    $reference = $candidate->references()->create([
        'type' => ReferenceType::Academic->value,
    ]);

    expect($reference->schema)->toBeNull();

    $sections = ReferenceFormRenderer::sectionsFor($reference, 'Acme Recruitment');
    $keys = collect($sections)->flatMap(fn (array $section) => collect($section['fields'])->pluck('key'));

    expect($keys->all())->toBe(['worked_from', 'worked_to']);
});

test('sectionsFor returns an empty array for a reference with neither a schema nor a legacy type', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => Company::factory()->create()->id]);
    $reference = $candidate->references()->create([]);

    expect(ReferenceFormRenderer::sectionsFor($reference, 'Acme'))->toBe([]);
});
