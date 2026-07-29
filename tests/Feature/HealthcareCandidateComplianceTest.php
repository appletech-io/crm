<?php

use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Models\HealthcareCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'healthcare');
});

test('the compliance tab shows the candidates medical information', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'has_health_condition_or_disability' => 'yes',
        'health_condition_details' => 'Asthma, carries an inhaler.',
        'reasonable_accommodations' => 'Ground floor ward where possible.',
    ]);

    $html = Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->toContain('Medical Information');
    expect($html)->toContain('Asthma, carries an inhaler.');
    expect($html)->toContain('Ground floor ward where possible.');
});

test('the compliance tab shows the candidates employment and conduct disclosures', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'dismissed_from_relevant_position' => 'yes',
        'dismissal_details' => 'Redundancy following restructure.',
        'subject_to_disciplinary_action' => 'no',
    ]);

    $html = Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->toContain('Employment &amp; Conduct');
    expect($html)->toContain('Redundancy following restructure.');
});

test('the compliance tab shows the candidates disclosure and rehabilitation of offenders answers', function () {
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'lived_overseas_six_months' => 'yes',
        'overseas_details' => 'Spain, 2019-2020.',
        'unspent_convictions' => 'no',
    ]);

    $html = Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->html();

    expect($html)->toContain('Disclosure &amp; Rehabilitation of Offenders');
    expect($html)->toContain('Spain, 2019-2020.');
});
