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

test('the healthcare references step lets a consultant add documents instead of showing a placeholder', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $status = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Vetting',
    ]);
    CandidateCandidateStatus::create([
        'model_type' => HealthcareCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);

    $html = Livewire::test(HealthcareVettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('References');
    expect($html)->toContain('Add Reference');
    expect($html)->not->toContain('This step has not been built yet.');
});

test('the documents step also shows a references summary with a link to the response once contacted', function () {
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $status = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Vetting',
    ]);
    CandidateCandidateStatus::create([
        'model_type' => HealthcareCandidate::class,
        'model_id' => $candidate->id,
        'candidate_status_id' => $status->id,
    ]);

    $candidate->references()->create([
        'type' => 'agency', 'first_name' => 'Ref', 'last_name' => 'Eree',
        'consent_to_contact' => true, 'status' => 'contacted',
        'token' => 'the-token', 'expires_on' => now()->addDays(7),
    ]);

    $html = Livewire::test(HealthcareVettingWizard::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('Ref Eree');
    expect($html)->toContain(route('reference.form', ['token' => 'the-token']));
});
