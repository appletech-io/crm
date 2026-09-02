<?php

use App\Enums\ReferenceStatus;
use App\Models\EducationCandidate;
use App\Services\References\ReferenceResponsePdfService;

test('generate produces a valid pdf for a submitted reference', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $reference = $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'job_title' => 'Headteacher',
        'consent_to_contact' => true,
        'contact_now' => true,
        'status' => ReferenceStatus::Submitted,
        'submitted_at' => now(),
        'answers' => [
            'worked_from' => '2018-01-01',
            'worked_to' => '2020-01-01',
            'safeguarding_issues' => 'yes',
            'safeguarding_details' => 'A minor incident, resolved.',
            'recommend_short_term' => 'yes',
            'recommend_long_term' => 'no',
            'employ_again' => 'yes',
            'rating_interaction_with_children' => 'excellent',
            'rating_ability_to_assist_teacher' => 'good',
            'rating_ability_to_work_on_own_initiative' => 'good',
            'rating_relationships_with_pupils' => 'excellent',
            'rating_relationships_with_staff' => 'excellent',
            'rating_suitability_for_supply_work' => 'good',
            'rating_timekeeping_punctuality' => 'excellent',
            'capacity_known' => 'Line manager',
            'confirm_name' => 'Ref Eree',
            'confirm_position' => 'Headteacher',
            'confirm_organisation' => 'Example School',
        ],
    ]);

    $pdf = (new ReferenceResponsePdfService)->generate($reference);

    expect($pdf)->toStartWith('%PDF');
});

test('generate skips a field hidden behind a show_when condition that was not met', function () {
    $candidate = EducationCandidate::factory()->create();

    $reference = $candidate->references()->create([
        'type' => 'agency',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'consent_to_contact' => true,
        'contact_now' => true,
        'status' => ReferenceStatus::Submitted,
        'submitted_at' => now(),
        'answers' => [
            'worked_from' => '2018-01-01',
            'worked_to' => '2020-01-01',
            'safeguarding_issues' => 'no',
            'confirm_name' => 'Ref Eree',
            'confirm_position' => 'Manager',
            'confirm_organisation' => 'Acme Agency',
        ],
    ]);

    // safeguarding_details only shows when safeguarding_issues is 'yes' — this
    // reference answered 'no', so generating the PDF must not choke on the
    // missing show_when-gated answer.
    $pdf = (new ReferenceResponsePdfService)->generate($reference);

    expect($pdf)->toStartWith('%PDF');
});

test('filename includes the candidate name and reference id', function () {
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $reference = $candidate->references()->create([
        'type' => 'academic',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'consent_to_contact' => true,
        'contact_now' => true,
        'status' => ReferenceStatus::Submitted,
        'submitted_at' => now(),
        'answers' => ['confirm_name' => 'Ref Eree'],
    ]);

    $filename = (new ReferenceResponsePdfService)->filename($reference);

    expect($filename)->toBe("reference-jane-doe-{$reference->id}.pdf");
});
