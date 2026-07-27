<?php

use App\Enums\DocumentType;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Models\CandidateDocument;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function actingAsPhotoTestUser(string $slug): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    $industry = Industry::factory()->create(['slug' => $slug]);
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    test()->actingAs($user);

    return $user;
}

test('education candidate edit page shows a placeholder when no photo is uploaded', function () {
    $user = actingAsPhotoTestUser('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('No photo uploaded.')
        ->assertDontSeeHtml('alt="Candidate photo"');
});

test('education candidate edit page shows the uploaded photo, including next to the header actions', function () {
    $user = actingAsPhotoTestUser('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    CandidateDocument::create([
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Photo,
        'path' => 'candidates/'.$candidate->id.'/photo.jpg',
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('No photo uploaded.')
        ->assertSeeHtml('alt="Candidate photo"');
});

test('healthcare candidate edit page shows a placeholder when no photo is uploaded', function () {
    $user = actingAsPhotoTestUser('healthcare');
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('No photo uploaded.')
        ->assertDontSeeHtml('alt="Candidate photo"');
});

test('healthcare candidate edit page shows the uploaded photo, including next to the header actions', function () {
    $user = actingAsPhotoTestUser('healthcare');
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $user->company_id]);

    CandidateDocument::create([
        'candidate_type' => HealthcareCandidate::class,
        'candidate_id' => $candidate->id,
        'document_type' => DocumentType::Photo,
        'path' => 'candidates/'.$candidate->id.'/photo.jpg',
    ]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('No photo uploaded.')
        ->assertSeeHtml('alt="Candidate photo"');
});
