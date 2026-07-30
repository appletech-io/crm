<?php

use App\Enums\DocumentType;
use App\Filament\Resources\HealthcareVetting\Pages\HealthcareVettingWizard;
use App\Filament\Resources\HealthcareVetting\Schemas\HealthcareVettingSteps;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateDocument;
use App\Models\CandidateStatus;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Carbon\CarbonInterface;
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

test('the references step shows a references summary with a link to the response once contacted', function () {
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

test('the personal details photo is resolved from the configured default filesystem disk, not a hardcoded one', function () {
    // Regression test: the photo URL used to hardcode Storage::disk('local'),
    // so it silently pointed at the wrong disk whenever FILESYSTEM_DISK
    // wasn't 'local' (e.g. 's3' in production), even though the same photo
    // rendered fine on the candidate edit form via config('filesystems.default').
    config(['filesystems.default' => 's3']);

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $path = 'candidates/'.$candidate->id.'/photo.jpg';
    CandidateDocument::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Photo,
        'path' => $path,
    ]);
    $candidate->load('documents');

    Storage::shouldReceive('disk')
        ->once()
        ->with('s3')
        ->andReturnSelf();
    Storage::shouldReceive('temporaryUrl')
        ->once()
        ->with($path, Mockery::type(CarbonInterface::class))
        ->andReturn('https://example-bucket.s3.amazonaws.com/signed-photo-url');

    $method = new ReflectionMethod(HealthcareVettingSteps::class, 'photoUrl');
    $method->setAccessible(true);

    expect($method->invoke(null, $candidate))->toBe('https://example-bucket.s3.amazonaws.com/signed-photo-url');
});
