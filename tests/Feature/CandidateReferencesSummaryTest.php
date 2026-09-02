<?php

use App\Filament\Widgets\CandidateReferencesSummary;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('widget renders with no references', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CandidateReferencesSummary::class, ['record' => $candidate])
        ->assertSuccessful()
        ->assertSee('No references');
});

test('it lists each reference with its name, type, and status', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $candidate->references()->create([
        'type' => 'agency', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'consent_to_contact' => true, 'status' => 'contacted',
    ]);

    Livewire::test(CandidateReferencesSummary::class, ['record' => $candidate])
        ->assertSuccessful()
        ->assertSee('Jane Doe')
        ->assertSee('Agency')
        ->assertSee('Contacted');
});

test('the view response action is hidden until the reference has a token', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $reference = $candidate->references()->create([
        'type' => 'agency', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'consent_to_contact' => true, 'status' => 'pending',
    ]);

    Livewire::test(CandidateReferencesSummary::class, ['record' => $candidate])
        ->assertActionHidden(TestAction::make('viewResponse')->table($reference));
});

test('the view response action links to the reference form once contacted', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $reference = $candidate->references()->create([
        'type' => 'agency', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'consent_to_contact' => true, 'status' => 'contacted',
        'token' => 'the-token', 'expires_on' => now()->addDays(7),
    ]);

    $html = Livewire::test(CandidateReferencesSummary::class, ['record' => $candidate])
        ->assertActionVisible(TestAction::make('viewResponse')->table($reference))
        ->html();

    expect($html)->toContain(route('reference.form', ['token' => 'the-token']));
});

test('the download pdf action is hidden until the reference has been submitted', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $reference = $candidate->references()->create([
        'type' => 'agency', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'consent_to_contact' => true, 'status' => 'contacted',
        'token' => 'the-token', 'expires_on' => now()->addDays(7),
    ]);

    Livewire::test(CandidateReferencesSummary::class, ['record' => $candidate])
        ->assertActionHidden(TestAction::make('downloadPdf')->table($reference));
});

test('the download pdf action is visible once the reference has been submitted', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $reference = $candidate->references()->create([
        'type' => 'agency', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'consent_to_contact' => true, 'status' => 'submitted',
        'token' => 'the-token', 'expires_on' => now()->addDays(7),
        'answers' => ['confirm_name' => 'Jane Doe'],
    ]);

    Livewire::test(CandidateReferencesSummary::class, ['record' => $candidate])
        ->assertActionVisible(TestAction::make('downloadPdf')->table($reference));
});

test('it works for healthcare candidates too', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $candidate->references()->create([
        'type' => 'professional', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'consent_to_contact' => true, 'status' => 'pending',
    ]);

    Livewire::test(CandidateReferencesSummary::class, ['record' => $candidate])
        ->assertSuccessful()
        ->assertSee('Jane Doe');
});
