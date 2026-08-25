<?php

use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\EducationCandidate;
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
});

function setCandidateSummaryActiveIndustry(string $slug): Industry
{
    $industry = Industry::factory()->create(['slug' => $slug]);
    Cache::put('user.'.test()->user->id.'.active_industry', $industry->slug);
    Cache::put('user.'.test()->user->id.'.active_industry_id', $industry->id);

    return $industry;
}

test('the quick view action mounts a fully populated education candidate summary without error', function () {
    setCandidateSummaryActiveIndustry('education');

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $this->user->id,
        'average_rating' => 4.5,
        'ratings_count' => 3,
        'key_stages' => ['keystage_1', 'keystage_2'],
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->mountTableAction('viewCandidateSummary', $candidate)
        ->assertSuccessful();
});

test('the quick view action mounts an education candidate summary with every optional field blank without error', function () {
    setCandidateSummaryActiveIndustry('education');

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => null,
        'phone' => null,
        'mobile' => null,
        'average_rating' => null,
        'payment_method' => null,
        'key_stages' => null,
    ]);

    Livewire::test(ListEducationCandidates::class)
        ->mountTableAction('viewCandidateSummary', $candidate)
        ->assertSuccessful();
});

test('the quick view action mounts a fully populated healthcare candidate summary without error', function () {
    setCandidateSummaryActiveIndustry('healthcare');

    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => $this->user->id,
        'average_rating' => 3.2,
        'ratings_count' => 5,
        'care_settings' => ['hospital', 'care_home'],
        'professional_registration_body' => 'NMC',
        'professional_registration_number' => '12A3456B',
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->mountTableAction('viewCandidateSummary', $candidate)
        ->assertSuccessful();
});

test('the quick view action mounts a healthcare candidate summary with every optional field blank without error', function () {
    setCandidateSummaryActiveIndustry('healthcare');

    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'consultant_id' => null,
        'phone' => null,
        'mobile' => null,
        'average_rating' => null,
        'payment_method' => null,
        'care_settings' => null,
        'professional_registration_body' => null,
        'professional_registration_number' => null,
    ]);

    Livewire::test(ListHealthcareCandidates::class)
        ->mountTableAction('viewCandidateSummary', $candidate)
        ->assertSuccessful();
});
