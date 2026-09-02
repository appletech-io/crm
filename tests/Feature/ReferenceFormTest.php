<?php

use App\Enums\ReferenceStatus;
use App\Models\CandidateReference;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\User;
use App\Services\ReferenceAccessSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function makeVerifiedReference(array $attributes = []): CandidateReference
{
    $candidate = EducationCandidate::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $reference = $candidate->references()->create(array_merge([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'email' => 'referee@example.com',
        'consent_to_contact' => true,
        'contact_now' => true,
        'status' => ReferenceStatus::Contacted,
        'token' => 'the-token-'.uniqid(),
        'expires_on' => now()->addDays(7),
    ], $attributes));

    ReferenceAccessSession::markVerified($reference->token);

    return $reference;
}

test('mount aborts 404 for an unknown token', function () {
    Livewire::test('reference.reference-form', ['token' => 'invalid-token'])
        ->assertStatus(404);
});

test('mount redirects to verify when this session has not verified the reference', function () {
    $candidate = EducationCandidate::factory()->create();
    $reference = $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Ref', 'last_name' => 'Eree',
        'email' => 'referee@example.com', 'consent_to_contact' => true, 'contact_now' => true,
        'status' => ReferenceStatus::Contacted, 'token' => 'unverified-token', 'expires_on' => now()->addDays(7),
    ]);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertRedirect(route('reference.verify', ['token' => $reference->token]));
});

test('it renders the agency form with dates and the safeguarding question', function () {
    $reference = makeVerifiedReference(['type' => 'agency']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('Worked From')
        ->assertSee('safeguarding, child protection or disciplinary')
        ->assertDontSee('Recommendations and engagement');
});

test('the safeguarding question names the candidates own company, not a hardcoded one', function () {
    $company = Company::factory()->create(['trading_name' => 'Bright Path Recruitment']);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $reference = $candidate->references()->create([
        'type' => 'agency',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'email' => 'referee@example.com',
        'consent_to_contact' => true,
        'contact_now' => true,
        'status' => ReferenceStatus::Contacted,
        'token' => 'the-token-'.uniqid(),
        'expires_on' => now()->addDays(7),
    ]);
    ReferenceAccessSession::markVerified($reference->token);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('Please inform Bright Path Recruitment of any safeguarding')
        ->assertDontSee('Applebough');
});

test('the page layout shows the candidates own company logo, not the default', function () {
    // A full HTTP request, not Livewire::test(), because the surrounding
    // layout (where the logo lives) is only rendered as part of the real
    // page response — a component test only returns the component's own
    // markup, never the layout it's wrapped in.
    Storage::fake('local');
    Storage::disk('local')->put('company-logos/acme.png', 'fake logo contents');
    $company = Company::factory()->create(['logo' => 'company-logos/acme.png']);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $reference = $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'email' => 'referee@example.com',
        'consent_to_contact' => true,
        'contact_now' => true,
        'status' => ReferenceStatus::Contacted,
        'token' => 'the-token-'.uniqid(),
        'expires_on' => now()->addDays(7),
    ]);
    ReferenceAccessSession::markVerified($reference->token);

    $this->get(route('reference.form', ['token' => $reference->token]))
        ->assertOk()
        ->assertSee(route('company.logo', $company), false)
        ->assertDontSee(asset('images/appletech.png'), false);
});

test('it renders the academic form with only the dates', function () {
    $reference = makeVerifiedReference(['type' => 'academic']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('Known From')
        ->assertDontSee('safeguarding, child protection or disciplinary');
});

test('it renders the character form with the suitability question', function () {
    $reference = makeVerifiedReference(['type' => 'character']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('suitable for the role');
});

test('character confirmation only asks for a name, not position or organisation', function () {
    $reference = makeVerifiedReference(['type' => 'character']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertDontSee('School / Organisation Name');
});

test('agency confirmation asks for position and organisation as well as name', function () {
    $reference = makeVerifiedReference(['type' => 'agency']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('School / Organisation Name');
});

test('it renders the professional form with the recommendation grid and rating grid', function () {
    $reference = makeVerifiedReference(['type' => 'professional']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('Recommendations and engagement')
        ->assertSee('Please rate the above named candidate in the following categories')
        ->assertSee('Interaction with Children')
        ->assertSee('Timekeeping / Punctuality');
});

test('submitting the agency form without required fields fails validation', function () {
    $reference = makeVerifiedReference(['type' => 'agency']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->call('submit')
        ->assertHasErrors(['answers.worked_from', 'answers.worked_to', 'answers.safeguarding_issues', 'answers.confirm_name']);

    expect($reference->fresh()->status)->toBe(ReferenceStatus::Contacted);
});

test('safeguarding details are required only when safeguarding_issues is yes', function () {
    $reference = makeVerifiedReference(['type' => 'agency']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->set('answers.worked_from', '2020-01-01')
        ->set('answers.worked_to', '2021-01-01')
        ->set('answers.safeguarding_issues', 'no')
        ->set('answers.confirm_name', 'Ref Eree')
        ->set('answers.confirm_position', 'Manager')
        ->set('answers.confirm_organisation', 'Acme Agency')
        ->call('submit')
        ->assertHasNoErrors();

    expect($reference->fresh()->status)->toBe(ReferenceStatus::Submitted);
});

test('safeguarding details are required when safeguarding_issues is yes', function () {
    $reference = makeVerifiedReference(['type' => 'agency']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->set('answers.worked_from', '2020-01-01')
        ->set('answers.worked_to', '2021-01-01')
        ->set('answers.safeguarding_issues', 'yes')
        ->set('answers.confirm_name', 'Ref Eree')
        ->set('answers.confirm_position', 'Manager')
        ->set('answers.confirm_organisation', 'Acme Agency')
        ->call('submit')
        ->assertHasErrors(['answers.safeguarding_details']);

    expect($reference->fresh()->status)->toBe(ReferenceStatus::Contacted);
});

test('submitting the character form only requires the confirm name, not position or organisation', function () {
    $reference = makeVerifiedReference(['type' => 'character']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->set('answers.worked_from', '2020-01-01')
        ->set('answers.worked_to', '2021-01-01')
        ->set('answers.suitable_for_role', 'yes')
        ->set('answers.confirm_name', 'Ref Eree')
        ->call('submit')
        ->assertHasNoErrors();

    expect($reference->fresh()->status)->toBe(ReferenceStatus::Submitted);
});

test('a submitted reference stores all of the answers and the submitted_at timestamp', function () {
    $reference = makeVerifiedReference(['type' => 'academic']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->set('answers.worked_from', '2018-09-01')
        ->set('answers.worked_to', '2020-07-01')
        ->set('answers.confirm_name', 'Ref Eree')
        ->set('answers.confirm_position', 'Head of Department')
        ->set('answers.confirm_organisation', 'Example University')
        ->call('submit')
        ->assertHasNoErrors();

    $reference->refresh();

    expect($reference->status)->toBe(ReferenceStatus::Submitted);
    expect($reference->submitted_at)->not->toBeNull();
    expect($reference->answers)->toBe([
        'worked_from' => '2018-09-01',
        'worked_to' => '2020-07-01',
        'confirm_name' => 'Ref Eree',
        'confirm_position' => 'Head of Department',
        'confirm_organisation' => 'Example University',
    ]);
});

test('submitting a reference logs a candidate activity noting it was completed', function () {
    $reference = makeVerifiedReference(['type' => 'academic']);
    $candidate = $reference->candidate;

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->set('answers.worked_from', '2018-09-01')
        ->set('answers.worked_to', '2020-07-01')
        ->set('answers.confirm_name', 'Ref Eree')
        ->set('answers.confirm_position', 'Head of Department')
        ->set('answers.confirm_organisation', 'Example University')
        ->call('submit')
        ->assertHasNoErrors();

    $activity = $candidate->activities()->latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->note)->toBe('Reference completed');
    expect($activity->body)->toContain('Ref Eree');
});

test('saving a reference again without changing its status does not log a duplicate completed activity', function () {
    $reference = makeVerifiedReference(['type' => 'academic']);
    $candidate = $reference->candidate;

    $reference->update(['status' => ReferenceStatus::Submitted, 'submitted_at' => now()]);
    expect($candidate->activities()->count())->toBe(1);

    $reference->update(['last_contacted' => now()->toDateString()]);
    expect($candidate->activities()->count())->toBe(1);
});

test('submitting the professional form validates every question in the ratings grid', function () {
    $reference = makeVerifiedReference(['type' => 'professional']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->set('answers.safeguarding_issues', 'no')
        ->set('answers.recommend_short_term', 'yes')
        ->set('answers.recommend_long_term', 'yes')
        ->set('answers.employ_again', 'yes')
        ->set('answers.capacity_known', 'Line manager')
        ->set('answers.confirm_name', 'Ref Eree')
        ->set('answers.confirm_position', 'Headteacher')
        ->set('answers.confirm_organisation', 'Example School')
        ->call('submit')
        ->assertHasErrors([
            'answers.rating_interaction_with_children',
            'answers.rating_ability_to_assist_teacher',
            'answers.rating_ability_to_work_on_own_initiative',
            'answers.rating_relationships_with_pupils',
            'answers.rating_relationships_with_staff',
            'answers.rating_suitability_for_supply_work',
            'answers.rating_timekeeping_punctuality',
        ]);

    expect($reference->fresh()->status)->toBe(ReferenceStatus::Contacted);
});

test('a fully completed professional form submits successfully', function () {
    $reference = makeVerifiedReference(['type' => 'professional']);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->set('answers.worked_from', '2018-01-01')
        ->set('answers.worked_to', '2020-01-01')
        ->set('answers.safeguarding_issues', 'no')
        ->set('answers.recommend_short_term', 'yes')
        ->set('answers.recommend_long_term', 'yes')
        ->set('answers.employ_again', 'yes')
        ->set('answers.rating_interaction_with_children', 'excellent')
        ->set('answers.rating_ability_to_assist_teacher', 'good')
        ->set('answers.rating_ability_to_work_on_own_initiative', 'good')
        ->set('answers.rating_relationships_with_pupils', 'excellent')
        ->set('answers.rating_relationships_with_staff', 'excellent')
        ->set('answers.rating_suitability_for_supply_work', 'good')
        ->set('answers.rating_timekeeping_punctuality', 'excellent')
        ->set('answers.capacity_known', 'Line manager')
        ->set('answers.confirm_name', 'Ref Eree')
        ->set('answers.confirm_position', 'Headteacher')
        ->set('answers.confirm_organisation', 'Example School')
        ->call('submit')
        ->assertHasNoErrors();

    expect($reference->fresh()->status)->toBe(ReferenceStatus::Submitted);
});

test('an already-submitted reference cannot be submitted again and is shown as read-only', function () {
    $reference = makeVerifiedReference([
        'type' => 'academic',
        'status' => ReferenceStatus::Submitted,
        'submitted_at' => now(),
        'answers' => ['worked_from' => '2018-01-01', 'worked_to' => '2019-01-01', 'confirm_name' => 'Ref Eree'],
    ]);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('can no longer be edited')
        ->set('answers.worked_from', '2099-01-01')
        ->call('submit');

    expect($reference->fresh()->answers['worked_from'])->toBe('2018-01-01');
});

test('a logged-in CRM user can see the filled-in answers of a submitted reference without going through verification', function () {
    $this->seed(RoleSeeder::class);

    $reference = makeVerifiedReference([
        'type' => 'academic',
        'status' => ReferenceStatus::Submitted,
        'submitted_at' => now(),
        'answers' => ['worked_from' => '2018-01-01', 'worked_to' => '2019-01-01', 'confirm_name' => 'Ref Eree'],
    ]);

    session()->forget("reference.{$reference->token}.verified");

    $consultant = User::factory()->create(['company_id' => $reference->candidate->company_id]);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('can no longer be edited')
        ->assertSet('answers.confirm_name', 'Ref Eree');
});

test('a logged-in CRM user sees a not-submitted notice and cannot fill the form in on the referees behalf', function () {
    $this->seed(RoleSeeder::class);

    $reference = makeVerifiedReference(['type' => 'agency']);
    session()->forget("reference.{$reference->token}.verified");

    $admin = User::factory()->create(['company_id' => $reference->candidate->company_id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful()
        ->assertSee('has not been submitted by the referee yet')
        ->set('answers.worked_from', '2020-01-01')
        ->call('submit');

    expect($reference->fresh()->status)->toBe(ReferenceStatus::Contacted);
    expect($reference->fresh()->answers)->toBeNull();
});

test('a logged-in CRM user can view an expired, unsubmitted reference', function () {
    $this->seed(RoleSeeder::class);

    $reference = makeVerifiedReference(['type' => 'agency', 'expires_on' => now()->subDay()]);
    session()->forget("reference.{$reference->token}.verified");

    $admin = User::factory()->create(['company_id' => $reference->candidate->company_id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSuccessful();
});

test('a staff viewer sees a download pdf button once the reference is submitted', function () {
    $this->seed(RoleSeeder::class);

    $reference = makeVerifiedReference([
        'type' => 'academic',
        'status' => ReferenceStatus::Submitted,
        'submitted_at' => now(),
        'answers' => ['worked_from' => '2018-01-01', 'worked_to' => '2019-01-01', 'confirm_name' => 'Ref Eree'],
    ]);
    session()->forget("reference.{$reference->token}.verified");

    $admin = User::factory()->create(['company_id' => $reference->candidate->company_id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertSee('Download PDF');
});

test('a staff viewer does not see a download pdf button before the reference is submitted', function () {
    $this->seed(RoleSeeder::class);

    $reference = makeVerifiedReference(['type' => 'agency']);
    session()->forget("reference.{$reference->token}.verified");

    $admin = User::factory()->create(['company_id' => $reference->candidate->company_id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertDontSee('Download PDF');
});

test('the referee themselves never sees a download pdf button', function () {
    $reference = makeVerifiedReference([
        'type' => 'academic',
        'status' => ReferenceStatus::Submitted,
        'submitted_at' => now(),
        'answers' => ['worked_from' => '2018-01-01', 'worked_to' => '2019-01-01', 'confirm_name' => 'Ref Eree'],
    ]);

    Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->assertDontSee('Download PDF');
});

test('calling downloadPdf as a staff viewer on a submitted reference streams a pdf', function () {
    $this->seed(RoleSeeder::class);

    $reference = makeVerifiedReference([
        'type' => 'academic',
        'status' => ReferenceStatus::Submitted,
        'submitted_at' => now(),
        'answers' => ['worked_from' => '2018-01-01', 'worked_to' => '2019-01-01', 'confirm_name' => 'Ref Eree'],
    ]);
    session()->forget("reference.{$reference->token}.verified");

    $admin = User::factory()->create(['company_id' => $reference->candidate->company_id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $component = Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->call('downloadPdf');

    $download = $component->effects['download'] ?? null;

    expect($download)->not->toBeNull();
    expect(base64_decode($download['content']))->toStartWith('%PDF');
});

test('calling downloadPdf as the referee does not trigger a download', function () {
    $reference = makeVerifiedReference([
        'type' => 'academic',
        'status' => ReferenceStatus::Submitted,
        'submitted_at' => now(),
        'answers' => ['worked_from' => '2018-01-01', 'worked_to' => '2019-01-01', 'confirm_name' => 'Ref Eree'],
    ]);

    $component = Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->call('downloadPdf');

    expect($component->effects['download'] ?? null)->toBeNull();
});

test('calling downloadPdf on an unsubmitted reference does not trigger a download', function () {
    $this->seed(RoleSeeder::class);

    $reference = makeVerifiedReference(['type' => 'agency']);
    session()->forget("reference.{$reference->token}.verified");

    $admin = User::factory()->create(['company_id' => $reference->candidate->company_id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $component = Livewire::test('reference.reference-form', ['token' => $reference->token])
        ->call('downloadPdf');

    expect($component->effects['download'] ?? null)->toBeNull();
});
