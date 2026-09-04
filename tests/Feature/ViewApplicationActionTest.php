<?php

use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\EducationCandidates\Pages\ViewApplication as EducationViewApplication;
use App\Filament\Resources\HealthcareCandidates\HealthcareCandidateResource;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\ViewApplication as HealthcareViewApplication;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\ReferenceForm;
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

test('the application complete badge links to the view application page and opens in a new tab', function () {
    activateIndustryFor('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'completed',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
        'completed_at' => now(),
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    $url = EducationCandidateResource::getUrl('view-application', ['record' => $candidate]);

    expect($html)->toContain('href="'.$url.'"');
    expect($html)->toContain('target="_blank"');
});

test('the view application page is not reachable-looking (no badge link) until the application is complete', function () {
    activateIndustryFor('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'pending',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
    ]);

    $html = Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    $url = EducationCandidateResource::getUrl('view-application', ['record' => $candidate]);

    expect($html)->not->toContain('href="'.$url.'"');
});

test('the education view application page shows the candidates submitted personal details, employment history and references', function () {
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

    Livewire::test(EducationViewApplication::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Jane')
        ->assertSee('Applicant')
        ->assertSee('Oakwood Primary')
        ->assertSee('Class Teacher')
        ->assertSee('Ref')
        ->assertSee('Eree');
});

test('the education view application page shows the reference type for both legacy and dynamic-form references', function () {
    activateIndustryFor('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'completed',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
        'completed_at' => now(),
    ]);

    $candidate->references()->create([
        'type' => 'professional',
        'first_name' => 'Legacy',
        'last_name' => 'Referee',
        'consent_to_contact' => true,
    ]);

    $form = ReferenceForm::factory()->create([
        'company_id' => $this->user->company_id,
        'name' => 'Character Reference',
    ]);
    $candidate->references()->create([
        'reference_form_id' => $form->id,
        'first_name' => 'Dynamic',
        'last_name' => 'Referee',
        'consent_to_contact' => true,
    ]);

    Livewire::test(EducationViewApplication::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Professional')
        ->assertSee('Character Reference');
});

test('the education view application page shows the terms of engagement, kcsie and declaration the candidate agreed to', function () {
    activateIndustryFor('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'completed',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
        'completed_at' => now(),
        'terms_of_engagement_accepted_at' => '2026-01-05 10:00:00',
        'terms_accepted_at' => '2026-01-05 10:05:00',
        'declaration_accepted_at' => '2026-01-05 10:10:00',
    ]);

    $html = Livewire::test(EducationViewApplication::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('05/01/2026');
    // Terms of Engagement clause text, reused from the live application form.
    expect($html)->toContain('Definitions &amp; Interpretation');
    // Declaration text, reused from the live application form.
    expect($html)->toContain('I agree to references being sought.');
    // KCSIE is an actual PDF document, embedded directly.
    expect($html)->toContain(asset('documents/kcsie.pdf'));
});

test('the education view application page tolerates a key_stages or availability value stored as a bare string instead of an array', function () {
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

    Livewire::test(EducationViewApplication::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Keystage 1')
        ->assertSee('Permanent');
});

test('the healthcare application complete badge links to the view application page and opens in a new tab', function () {
    activateIndustryFor('healthcare');
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'pending',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
    ]);

    $urlWhilePending = HealthcareCandidateResource::getUrl('view-application', ['record' => $candidate]);

    $htmlWhilePending = Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($htmlWhilePending)->not->toContain('href="'.$urlWhilePending.'"');

    $candidate->application->update(['status' => 'completed', 'completed_at' => now()]);

    $html = Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->html();

    expect($html)->toContain('href="'.$urlWhilePending.'"');
    expect($html)->toContain('target="_blank"');
});

test('the healthcare view application page shows the candidates submitted details', function () {
    activateIndustryFor('healthcare');
    $candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->user->company_id,
        'first_name' => 'John',
        'last_name' => 'Nurse',
    ]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'completed',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
        'completed_at' => now(),
    ]);

    Livewire::test(HealthcareViewApplication::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('John')
        ->assertSee('Nurse');
});
