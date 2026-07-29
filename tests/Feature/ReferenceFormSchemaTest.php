<?php

use App\Enums\ReferenceType;
use App\Services\References\ReferenceFormSchema;

test('agency asks for dates worked and a safeguarding question with a conditional detail field', function () {
    $sections = ReferenceFormSchema::sectionsFor(ReferenceType::Agency);
    $keys = collect($sections)->flatMap(fn (array $section) => collect($section['fields'])->pluck('key'));

    expect($keys->all())->toBe(['worked_from', 'worked_to', 'safeguarding_issues', 'safeguarding_details']);

    $rules = ReferenceFormSchema::rulesFor(ReferenceType::Agency);
    expect($rules['answers.safeguarding_details'])->toContain('required_if:answers.safeguarding_issues,yes');
});

test('academic only asks for dates', function () {
    $sections = ReferenceFormSchema::sectionsFor(ReferenceType::Academic);
    $keys = collect($sections)->flatMap(fn (array $section) => collect($section['fields'])->pluck('key'));

    expect($keys->all())->toBe(['worked_from', 'worked_to']);
});

test('character asks for dates and a suitability question with a conditional detail field when not suitable', function () {
    $sections = ReferenceFormSchema::sectionsFor(ReferenceType::Character);
    $keys = collect($sections)->flatMap(fn (array $section) => collect($section['fields'])->pluck('key'));

    expect($keys->all())->toBe(['worked_from', 'worked_to', 'suitable_for_role', 'suitability_details']);

    $rules = ReferenceFormSchema::rulesFor(ReferenceType::Character);
    expect($rules['answers.suitability_details'])->toContain('required_if:answers.suitable_for_role,no');
});

test('professional includes the safeguarding question, the recommendation grid, the rating grid, and free text questions', function () {
    $sections = ReferenceFormSchema::sectionsFor(ReferenceType::Professional);
    $headings = collect($sections)->pluck('heading')->all();

    expect($headings)->toBe([null, 'Recommendations and engagement', 'Please rate the above named candidate in the following categories', null]);

    $keys = collect($sections)->flatMap(fn (array $section) => collect($section['fields'])->pluck('key'))->all();

    expect($keys)->toBe([
        'safeguarding_issues',
        'safeguarding_details',
        'recommend_short_term',
        'recommend_long_term',
        'employ_again',
        'rating_interaction_with_children',
        'rating_ability_to_assist_teacher',
        'rating_ability_to_work_on_own_initiative',
        'rating_relationships_with_pupils',
        'rating_relationships_with_staff',
        'rating_suitability_for_supply_work',
        'rating_timekeeping_punctuality',
        'capacity_known',
        'employment_breaks',
    ]);
});

test('professional recommendation and rating fields offer an N/A option', function () {
    $sections = ReferenceFormSchema::sectionsFor(ReferenceType::Professional);

    $recommendField = $sections[1]['fields'][0];
    expect($recommendField['options'])->toBe(['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A']);

    $ratingField = $sections[2]['fields'][0];
    expect($ratingField['options'])->toBe([
        'excellent' => 'Excellent',
        'good' => 'Good',
        'average' => 'Average',
        'below_avg' => 'Below Avg',
        'poor' => 'Poor',
        'na' => 'N/A',
    ]);
});

test('employment breaks is the only optional field on the professional form', function () {
    $rules = ReferenceFormSchema::rulesFor(ReferenceType::Professional);

    expect($rules['answers.employment_breaks'])->toContain('nullable');
    expect($rules['answers.capacity_known'])->toContain('required');
});

test('only character skips the position and organisation confirmation fields', function () {
    expect(ReferenceFormSchema::needsPositionAndOrganisation(ReferenceType::Character))->toBeFalse();
    expect(ReferenceFormSchema::needsPositionAndOrganisation(ReferenceType::Agency))->toBeTrue();
    expect(ReferenceFormSchema::needsPositionAndOrganisation(ReferenceType::Professional))->toBeTrue();
    expect(ReferenceFormSchema::needsPositionAndOrganisation(ReferenceType::Academic))->toBeTrue();
});
