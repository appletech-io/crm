<?php

use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
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
