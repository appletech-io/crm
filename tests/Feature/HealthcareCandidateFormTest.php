<?php

use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Models\Booking;
use App\Models\Client;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\ReferenceForm;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", 'healthcare');
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('the bookings tab is hidden for a candidate with no bookings', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('Bookings');
});

test('the bookings tab lists the client, dates worked, and agency for each booking', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $client = Client::factory()->create(['company_id' => $candidate->company_id]);
    Booking::factory()->create([
        'company_id' => $candidate->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => $candidate::class,
        'start_date' => '2026-09-07',
        'end_date' => '2026-09-11',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Bookings')
        ->assertSee($client->name)
        ->assertSee($candidate->company->name)
        ->assertSee('Sep 7, 2026')
        ->assertSee('Sep 11, 2026');
});

test('the formatted CV content can be edited and saved from its tab', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->formattedCv()->create(['content' => '<p>Original content</p>']);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'formattedCv' => ['content' => '<p>Edited content</p>'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->formattedCv()->first()->content)->toBe('<p>Edited content</p>');
});

test('saving the candidate form regenerates the formatted CV pdf from the saved content', function () {
    Storage::fake('local');

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    Storage::disk('local')->put('test-cvs/cv.pdf', 'fake pdf');
    $candidate->documents()->create(['document_type' => 'cv', 'path' => 'test-cvs/cv.pdf']);
    $candidate->formattedCv()->create(['content' => '<p>Some content</p>']);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'formattedCv' => ['content' => '<p>Some content</p>'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $pdfPath = $candidate->formattedCv()->first()->pdf_path;

    expect($pdfPath)->not->toBeNull();
    Storage::disk('local')->assertExists($pdfPath);
});

test('the documents tab renders for a healthcare candidate', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful();
});

test('an NI number can be saved on the personal details tab', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['ni_number' => 'QQ123456C'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->ni_number)->toBe('QQ123456C');
});

test('an invalid NI number is rejected on the personal details tab', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['ni_number' => 'not-a-real-ni-number'])
        ->call('save')
        ->assertHasFormErrors(['ni_number' => 'regex']);
});

test('a UK landline number is accepted in the phone field', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'phone' => '01234 567890',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->phone)->toBe('01234 567890');
});

test('a UK landline number is rejected in the mobile field', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'mobile' => '01234 567890',
        ])
        ->call('save')
        ->assertHasFormErrors(['mobile' => 'regex']);
});

test('a UK mobile number is accepted in the mobile field', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'mobile' => '07700900000',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->mobile)->toBe('07700900000');
});

test('compliance expiry dates can be edited inline from the candidate edit page', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'passport',
        'dbs_certificate_number' => '001234567890',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'dbs_expiry_date' => '2029-03-01',
            'right_to_work_expiry_date' => '2027-01-01',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();
    expect($candidate->dbs_expiry_date->toDateString())->toBe('2029-03-01');
    expect($candidate->right_to_work_expiry_date->toDateString())->toBe('2027-01-01');
});

test('the right to work expiry date field is hidden and not saved when right to work type is birth certificate', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'birth_certificate',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldDoesNotExist('right_to_work_expiry_date')
        ->fillForm(['dbs_expiry_date' => '2029-03-01'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->right_to_work_expiry_date)->toBeNull();
});

test('right to work, dbs, and professional registration compliance fields can be edited inline from the candidate edit page', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'visa',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'visa_share_code' => 'ABC123DEF456',
            'visa_issue_date' => '2024-01-01',
            'visa_notes' => 'Skilled worker visa.',
            'right_to_work_checked' => 'yes',
            'right_to_work_checked_date' => '2024-01-15',
            'has_naric' => 'yes',
            'has_dbs' => 'yes',
            'dbs_checked_date' => '2024-02-01',
            'dbs_certificate_number' => '001234567890',
            'professional_registration_body' => 'NMC',
            'professional_registration_number' => 'NM123456A',
            'professional_registration_checked_at' => '2024-03-01',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();
    expect($candidate->visa_share_code)->toBe('ABC123DEF456');
    expect($candidate->visa_issue_date->toDateString())->toBe('2024-01-01');
    expect($candidate->visa_notes)->toBe('Skilled worker visa.');
    expect($candidate->right_to_work_checked)->toBe('yes');
    expect($candidate->right_to_work_checked_date->toDateString())->toBe('2024-01-15');
    expect($candidate->has_naric)->toBe('yes');
    expect($candidate->has_dbs)->toBe('yes');
    expect($candidate->dbs_checked_date->toDateString())->toBe('2024-02-01');
    expect($candidate->dbs_certificate_number)->toBe('001234567890');
    expect($candidate->professional_registration_body)->toBe('NMC');
    expect($candidate->professional_registration_number)->toBe('NM123456A');
    expect($candidate->professional_registration_checked_at->toDateString())->toBe('2024-03-01');
});

test('visa fields are hidden and not saved when right to work type is passport', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'passport',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldDoesNotExist('visa_share_code')
        ->assertFormFieldDoesNotExist('visa_notes')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->visa_share_code)->toBeNull();
});

test('the overseas police clearance check field only appears once lived overseas six months is set to yes', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'lived_overseas_six_months' => 'no',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldDoesNotExist('overseas_police_clearance_check')
        ->fillForm(['lived_overseas_six_months' => 'yes'])
        ->assertFormFieldExists('overseas_police_clearance_check');
});

test('a gap/statement reference does not require a name when saving via the repeater', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id, 'phone' => '07700900000']);

    $statementForm = ReferenceForm::factory()->statementOnly()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $reference = $candidate->references()->create([
        'reference_form_id' => $statementForm->id,
        'statement' => 'Travelling',
        'worked_from' => '2024-01-01',
        'worked_to' => '2024-06-01',
        'status' => 'confirmed',
    ])->fresh();

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set("data.references.record-{$reference->id}.statement", 'Travelling around Europe')
        ->call('save')
        ->assertHasNoFormErrors();

    $reference->refresh();
    expect($reference->statement)->toBe('Travelling around Europe');
    expect($reference->first_name)->toBeNull();
});

test('switching an existing reference to gap/statement requires a statement instead of a name', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id, 'phone' => '07700900000']);

    $statementForm = ReferenceForm::factory()->statementOnly()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ])->fresh();

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set("data.references.record-{$reference->id}.reference_form_id", $statementForm->id)
        ->call('save')
        ->assertHasFormErrors(["references.record-{$reference->id}.statement"]);
});
