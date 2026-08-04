<?php

use App\Filament\Resources\HealthcareVetting\Pages\HealthcareVettingWizard;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateStatus;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

function assignHealthcareVettingStatus(HealthcareCandidate $candidate, Industry $industry, int $companyId): void
{
    $status = CandidateStatus::factory()->create([
        'company_id' => $companyId,
        'industry_id' => $industry->id,
        'name' => 'Vetting',
    ]);
    CandidateCandidateStatus::create([
        'model_type' => HealthcareCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);
}

test('healthcare vetting wizard can save security checks including right to work expiry date', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'visa',
        'phone' => '07123456789',
    ]);
    assignHealthcareVettingStatus($candidate, $this->industry, $this->user->company_id);

    Livewire::test(HealthcareVettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'visa_issue_date' => '2025-01-01',
            'visa_expiry_date' => '2027-01-01',
            'right_to_work_expiry_date' => '2027-01-01',
            'visa_notes' => 'Skilled worker visa, sponsor confirmed.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->visa_issue_date->toDateString())->toBe('2025-01-01');
    expect($candidate->visa_expiry_date->toDateString())->toBe('2027-01-01');
    expect($candidate->right_to_work_expiry_date->toDateString())->toBe('2027-01-01');
    expect($candidate->visa_notes)->toBe('Skilled worker visa, sponsor confirmed.');
});

test('the right to work document expiry date section shows for visa and passport but not birth certificate', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'birth_certificate',
    ]);
    assignHealthcareVettingStatus($candidate, $this->industry, $this->user->company_id);

    $html = Livewire::test(HealthcareVettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->not->toContain('Right to Work Document');

    $candidate->update(['right_to_work_type' => 'passport']);

    $html = Livewire::test(HealthcareVettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('Right to Work Document');
});

test('healthcare vetting wizard can save the right to work expiry date for a passport candidate', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'right_to_work_type' => 'passport',
        'phone' => '07123456789',
    ]);
    assignHealthcareVettingStatus($candidate, $this->industry, $this->user->company_id);

    Livewire::test(HealthcareVettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['right_to_work_expiry_date' => '2030-05-01'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->refresh()->right_to_work_expiry_date->toDateString())->toBe('2030-05-01');
});

test('healthcare vetting wizard can save a new dbs certificate number, checked date and expiry date', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'dbs_certificate_number' => null,
        'phone' => '07123456789',
    ]);
    assignHealthcareVettingStatus($candidate, $this->industry, $this->user->company_id);

    Livewire::test(HealthcareVettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'dbs_certificate_number' => '001234567890',
            'dbs_checked_date' => '2026-03-01',
            'dbs_expiry_date' => '2029-03-01',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->dbs_certificate_number)->toBe('001234567890');
    expect($candidate->dbs_checked_date->toDateString())->toBe('2026-03-01');
    expect($candidate->dbs_expiry_date->toDateString())->toBe('2029-03-01');
});
