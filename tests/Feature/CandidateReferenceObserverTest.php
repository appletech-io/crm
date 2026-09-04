<?php

use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\ReferenceForm;

test('changing the reference form on a still-pending reference re-snapshots its schema', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => Company::factory()->create()->id]);
    $industry = Industry::factory()->create();

    $originalForm = ReferenceForm::factory()->create(['industry_id' => $industry->id]);
    $originalForm->fields()->create(['label' => 'Worked From', 'field_type' => 'date']);

    $newForm = ReferenceForm::factory()->create(['industry_id' => $industry->id]);
    $newForm->fields()->create(['label' => 'Known From', 'field_type' => 'date']);

    $reference = $candidate->references()->create([
        'reference_form_id' => $originalForm->id,
        'status' => 'pending',
    ]);

    expect($reference->schema[0]['fields'][0]['key'])->toBe('worked_from');

    $reference->update(['reference_form_id' => $newForm->id]);

    expect($reference->fresh()->reference_form_id)->toBe($newForm->id);
    expect($reference->fresh()->schema[0]['fields'][0]['key'])->toBe('known_from');
});

test('the reference form cannot be changed once the reference has been contacted', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => Company::factory()->create()->id]);
    $industry = Industry::factory()->create();

    $originalForm = ReferenceForm::factory()->create(['industry_id' => $industry->id]);
    $originalForm->fields()->create(['label' => 'Worked From', 'field_type' => 'date']);

    $newForm = ReferenceForm::factory()->create(['industry_id' => $industry->id]);

    $reference = $candidate->references()->create([
        'reference_form_id' => $originalForm->id,
        'status' => 'contacted',
    ]);

    $originalSchema = $reference->schema;

    $reference->update(['reference_form_id' => $newForm->id]);

    expect($reference->fresh()->reference_form_id)->toBe($originalForm->id);
    expect($reference->fresh()->schema)->toBe($originalSchema);
});

test('the reference form cannot be changed once the reference has been submitted', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => Company::factory()->create()->id]);
    $industry = Industry::factory()->create();

    $originalForm = ReferenceForm::factory()->create(['industry_id' => $industry->id]);
    $newForm = ReferenceForm::factory()->create(['industry_id' => $industry->id]);

    $reference = $candidate->references()->create([
        'reference_form_id' => $originalForm->id,
        'status' => 'submitted',
    ]);

    $reference->update(['reference_form_id' => $newForm->id]);

    expect($reference->fresh()->reference_form_id)->toBe($originalForm->id);
});

test('clearing the reference form on a still-pending reference also clears its schema', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => Company::factory()->create()->id]);
    $industry = Industry::factory()->create();

    $form = ReferenceForm::factory()->create(['industry_id' => $industry->id]);
    $form->fields()->create(['label' => 'Worked From', 'field_type' => 'date']);

    $reference = $candidate->references()->create([
        'reference_form_id' => $form->id,
        'status' => 'pending',
    ]);

    expect($reference->schema)->not->toBeNull();

    $reference->update(['reference_form_id' => null]);

    expect($reference->fresh()->schema)->toBeNull();
});
