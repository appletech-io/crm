<?php

use App\Enums\ReferenceStatus;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Models\EducationCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
});

test('edit page renders with tabs', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful();
});

test('personal details can be saved on candidate', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'gender' => 'female',
            'nationality' => 'British',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'London',
            'ni_number' => 'QQ123456C',
            'phone' => '07700900000',
            'mobile' => '07700900001',
            'postcode' => 'SW1A 1AA',
            'city' => 'London',
            'country' => 'United Kingdom',
            'emergency_contact_name' => 'John Doe',
            'emergency_contact_number' => '07700900002',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');
    expect($candidate->gender)->toBe('female');
    expect($candidate->ni_number)->toBe('QQ123456C');
    expect($candidate->emergency_contact_name)->toBe('John Doe');
});

test('an invalid NI number is rejected on the personal details tab', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['ni_number' => 'not-a-real-ni-number'])
        ->call('save')
        ->assertHasFormErrors(['ni_number' => 'regex']);
});

test('the formatted CV content can be edited and saved from its tab', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);
    $candidate->formattedCv()->create(['content' => '<p>Original content</p>']);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'formattedCv' => ['content' => '<p>Edited content</p>'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->formattedCv()->first()->content)->toBe('<p>Edited content</p>');
});

test('saving the candidate form regenerates the formatted CV pdf from the saved content', function () {
    Storage::fake('local');

    $candidate = EducationCandidate::factory()->create(['company_id' => null]);
    Storage::disk('local')->put('test-cvs/cv.pdf', 'fake pdf');
    $candidate->documents()->create(['document_type' => 'cv', 'path' => 'test-cvs/cv.pdf']);
    $candidate->formattedCv()->create(['content' => '<p>Some content</p>']);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'formattedCv' => ['content' => '<p>Some content</p>'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $pdfPath = $candidate->formattedCv()->first()->pdf_path;

    expect($pdfPath)->not->toBeNull();
    Storage::disk('local')->assertExists($pdfPath);
});

test('a UK landline number is accepted in the phone field', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'phone' => '01234 567890',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->phone)->toBe('01234 567890');
});

test('email must be unique among candidates in the same company', function () {
    EducationCandidate::factory()->create(['company_id' => null, 'email' => 'jane@example.com']);
    $candidate = EducationCandidate::factory()->create(['company_id' => null, 'email' => 'other@example.com']);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['email' => 'jane@example.com'])
        ->call('save')
        ->assertHasFormErrors(['email' => 'unique']);
});

test('a candidate keeps its own email as valid when saving unrelated fields', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null, 'email' => 'jane@example.com']);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'email' => 'jane@example.com',
            'first_name' => 'Updated',
            'phone' => '07700900000',
            'mobile' => '07700900001',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

test('references can be viewed and saved via the repeater on the candidate edit form', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldExists('references')
        ->assertSuccessful();

    expect($candidate->references()->count())->toBe(1);
});

test('a gap/statement reference does not require a name when saving via the repeater', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null, 'phone' => '07700900000']);

    $reference = $candidate->references()->create([
        'type' => 'gap_statement',
        'statement' => 'Travelling',
        'worked_from' => '2024-01-01',
        'worked_to' => '2024-06-01',
        'status' => 'confirmed',
    ])->fresh();

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set("data.references.record-{$reference->id}.statement", 'Travelling around Europe')
        ->call('save')
        ->assertHasNoFormErrors();

    $reference->refresh();
    expect($reference->statement)->toBe('Travelling around Europe');
    expect($reference->first_name)->toBeNull();
    expect($reference->last_name)->toBeNull();
});

test('switching an existing reference to gap/statement requires a statement instead of a name', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null, 'phone' => '07700900000']);

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ])->fresh();

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set("data.references.record-{$reference->id}.type", 'gap_statement')
        ->call('save')
        ->assertHasFormErrors(["references.record-{$reference->id}.statement"]);
});

test('collapsed reference item label shows a status emoji alongside the text', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'worked_from' => '2019-01-01',
        'status' => 'confirmed',
        'consent_to_contact' => true,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('✅')
        ->assertSee('Jane Smith — Confirmed ✅');
});

test('new references default to pending status and can be moved through the workflow via the repeater', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ])->fresh();

    expect($reference->status)->toBe(ReferenceStatus::Pending);
    expect($reference->last_contacted)->toBeNull();

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set('data.phone', '07700900000')
        ->set('data.mobile', '07700900001')
        ->set("data.references.record-{$reference->id}.status", 'confirmed')
        ->set("data.references.record-{$reference->id}.last_contacted", '2026-06-01')
        ->call('save')
        ->assertHasNoFormErrors();

    $reference->refresh();

    expect($reference->status)->toBe(ReferenceStatus::Confirmed);
    expect($reference->last_contacted->toDateString())->toBe('2026-06-01');
});

test('references default to contact_now being enabled and can be switched off via the repeater', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ])->fresh();

    expect($reference->contact_now)->toBeTrue();

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set('data.phone', '07700900000')
        ->set('data.mobile', '07700900001')
        ->set("data.references.record-{$reference->id}.contact_now", false)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($reference->refresh()->contact_now)->toBeFalse();
});

test('the view reference response action is hidden when the reference has not been contacted yet', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('View Reference Response');
});

test('the view reference response action links to the reference form once the reference has a token', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
        'status' => 'contacted',
        'token' => 'the-token',
        'expires_on' => now()->addDays(7),
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('View Reference Response')
        ->html();

    expect($html)->toContain(route('reference.form', ['token' => 'the-token']));
});

test('the resend action is labelled "send" before the reference has been contacted and "resend" after', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Never',
        'last_name' => 'Contacted',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
        'contact_now' => true,
        'email' => 'referee@example.com',
        'status' => 'pending',
    ]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Already',
        'last_name' => 'Contacted',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
        'contact_now' => true,
        'email' => 'referee2@example.com',
        'status' => 'contacted',
        'token' => 'the-token',
        'expires_on' => now()->addDays(7),
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Send Reference Email')
        ->assertSee('Resend Reference Email');
});

test('the resend action is hidden once a reference has been submitted', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
        'contact_now' => true,
        'email' => 'referee@example.com',
        'status' => 'submitted',
        'token' => 'the-token',
        'expires_on' => now()->addDays(7),
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('Send Reference Email')
        ->assertDontSee('Resend Reference Email');
});

test('the resend action is hidden when the referee should not be contacted yet', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
        'contact_now' => false,
        'email' => 'referee@example.com',
        'status' => 'pending',
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('Send Reference Email')
        ->assertDontSee('Resend Reference Email');
});

test('employment history can be viewed and saved via the repeater on the candidate edit form', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->employmentHistories()->create([
        'company_name' => 'Oakwood Primary',
        'job_title' => 'Class Teacher',
        'worked_from' => '2020-09-01',
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldExists('employmentHistories')
        ->assertSuccessful();

    expect($candidate->employmentHistories()->count())->toBe(1);
});

test('collapsed employment history item label shows the company and year range', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->employmentHistories()->create([
        'company_name' => 'Oakwood Primary',
        'job_title' => 'Class Teacher',
        'worked_from' => '2020-09-01',
        'worked_to' => '2022-07-01',
    ]);
    $candidate->employmentHistories()->create([
        'company_name' => 'Elm Secondary',
        'job_title' => 'Head of Year',
        'worked_from' => '2018-09-01',
        'worked_to' => null,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Oakwood Primary (2020 - 2022)')
        ->assertSee('Elm Secondary (2018 - Present)');
});

test('compliance expiry dates can be edited inline from the candidate edit page', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => null,
        'right_to_work_type' => 'visa',
        'dbs_certificate_number' => '001234567890',
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'dbs_expiry_date' => '2029-03-01',
            'right_to_work_expiry_date' => '2027-01-01',
            'safeguarding_expiry_date' => '2028-06-01',
            'benedicts_law_expiry_date' => '2027-09-01',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();
    expect($candidate->dbs_expiry_date->toDateString())->toBe('2029-03-01');
    expect($candidate->right_to_work_expiry_date->toDateString())->toBe('2027-01-01');
    expect($candidate->safeguarding_expiry_date->toDateString())->toBe('2028-06-01');
    expect($candidate->benedicts_law_expiry_date->toDateString())->toBe('2027-09-01');
});

test('the right to work expiry date field is hidden and not saved when right to work type is birth certificate', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => null,
        'right_to_work_type' => 'birth_certificate',
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldDoesNotExist('right_to_work_expiry_date')
        ->fillForm(['dbs_expiry_date' => '2029-03-01'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->right_to_work_expiry_date)->toBeNull();
});
