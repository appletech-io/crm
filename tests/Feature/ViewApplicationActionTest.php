<?php

use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
});

function activateIndustryFor(string $slug): void
{
    $industry = Industry::factory()->create(['slug' => $slug]);
    Cache::put('user.'.test()->user->id.'.active_industry', $industry->slug);
    Cache::put('user.'.test()->user->id.'.active_industry_id', $industry->id);
}

test('the application complete badge is clickable and the view application action is hidden until the application is complete', function () {
    activateIndustryFor('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'pending',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionHidden('viewApplication');
});

test('the education view application action shows the candidates submitted personal details, employment history and references', function () {
    activateIndustryFor('education');
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'Jane',
        'last_name' => 'Applicant',
    ]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'completed',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
        'completed_at' => now(),
    ]);
    $candidate->employmentHistories()->create([
        'company_name' => 'Oakwood Primary',
        'job_title' => 'Class Teacher',
        'worked_from' => '2020-09-01',
    ]);
    $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Ref',
        'last_name' => 'Eree',
        'consent_to_contact' => true,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionVisible('viewApplication')
        ->mountAction('viewApplication')
        ->assertSchemaStateSet([
            'first_name' => 'Jane',
            'last_name' => 'Applicant',
        ])
        ->assertMountedActionModalSee('Oakwood Primary')
        ->assertMountedActionModalSee('Class Teacher')
        ->assertMountedActionModalSee('Ref')
        ->assertMountedActionModalSee('Eree');
});

test('the education view application action tolerates a key_stages or availability value stored as a bare string instead of an array', function () {
    activateIndustryFor('education');
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->user->company_id,
    ]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'completed',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
        'completed_at' => now(),
    ]);

    // The 'array' cast happily JSON-encodes a bare string as a JSON string
    // literal, which then decodes back to a plain string on read — this
    // replicates that legacy-data shape rather than a proper array.
    $candidate->update(['key_stages' => 'keystage_1', 'availability' => 'permanent']);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->mountAction('viewApplication')
        ->assertMountedActionModalSee('Keystage 1')
        ->assertMountedActionModalSee('Permanent');
});

test('the healthcare view application action is hidden until the application is complete and shows submitted details once it is', function () {
    activateIndustryFor('healthcare');
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'John',
        'last_name' => 'Nurse',
    ]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'pending',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionHidden('viewApplication');

    $candidate->application->update(['status' => 'completed', 'completed_at' => now()]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionVisible('viewApplication')
        ->mountAction('viewApplication')
        ->assertSchemaStateSet([
            'first_name' => 'John',
            'last_name' => 'Nurse',
        ]);
});
