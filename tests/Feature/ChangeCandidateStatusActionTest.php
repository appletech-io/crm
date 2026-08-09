<?php

use App\Enums\ActivityType;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Models\CandidateStatus;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
});

function activateIndustry(User $user, string $slug): Industry
{
    $industry = Industry::factory()->create(['slug' => $slug]);
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    return $industry;
}

test('changing status on an education candidate with no existing status sets it and logs an activity', function () {
    $industry = activateIndustry($this->user, 'education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $shortlisted = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $industry->id,
        'name' => 'Shortlisted',
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->callAction('changeStatus', data: ['candidate_status_id' => $shortlisted->id])
        ->assertNotified('Status updated');

    $candidate->refresh();
    expect($candidate->statuses)->toHaveCount(1);
    expect($candidate->statuses->first()->candidate_status_id)->toBe($shortlisted->id);

    $activity = $candidate->activities()->latest()->first();
    expect($activity->type)->toBe(ActivityType::StatusChange);
    expect($activity->note)->toBe('Status set to "Shortlisted"');
    expect($activity->user_id)->toBe($this->user->id);
});

test('changing status on an education candidate replaces the existing status instead of adding a second one', function () {
    $industry = activateIndustry($this->user, 'education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $onboarding = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $industry->id,
        'name' => 'Onboarding',
    ]);
    $placed = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $industry->id,
        'name' => 'Placed',
    ]);
    $candidate->statuses()->create(['candidate_status_id' => $onboarding->id]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->callAction('changeStatus', data: [
            'candidate_status_id' => $placed->id,
            'note' => 'Confirmed start date with the school.',
        ])
        ->assertNotified('Status updated');

    $candidate->refresh();
    expect($candidate->statuses)->toHaveCount(1);
    expect($candidate->statuses->first()->candidate_status_id)->toBe($placed->id);

    $activity = $candidate->activities()->latest()->first();
    expect($activity->type)->toBe(ActivityType::StatusChange);
    expect($activity->note)->toBe('Status changed from "Onboarding" to "Placed": Confirmed start date with the school.');
});

test('the status select only offers statuses for the active industry', function () {
    $industry = activateIndustry($this->user, 'education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $matching = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $industry->id,
        'name' => 'Matching Status',
    ]);
    $otherIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $otherIndustry->id,
        'name' => 'Other Industry Status',
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->mountAction('changeStatus')
        ->assertFormFieldExists('candidate_status_id', function (Select $field) use ($matching): bool {
            $options = $field->getOptions();

            return in_array($matching->name, $options, true) && ! in_array('Other Industry Status', $options, true);
        });
});

test('a consultant cannot see or call the change status action', function () {
    $industry = activateIndustry($this->user, 'education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", $industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $industry->id);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionHidden('changeStatus');

    expect($candidate->fresh()->statuses)->toHaveCount(0);
});

test('a compliance user can see and call the change status action', function () {
    $industry = activateIndustry($this->user, 'education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $shortlisted = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $industry->id,
        'name' => 'Shortlisted',
    ]);

    $compliance = User::factory()->create(['company_id' => $this->user->company_id]);
    $compliance->assignRole('compliance');
    $this->actingAs($compliance);
    Cache::put("user.{$compliance->id}.active_industry", $industry->slug);
    Cache::put("user.{$compliance->id}.active_industry_id", $industry->id);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertActionVisible('changeStatus')
        ->callAction('changeStatus', data: ['candidate_status_id' => $shortlisted->id])
        ->assertNotified('Status updated');

    expect($candidate->fresh()->statuses)->toHaveCount(1);
});

test('changing status on a healthcare candidate replaces the existing status and logs an activity', function () {
    $industry = activateIndustry($this->user, 'healthcare');
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $registered = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $industry->id,
        'name' => 'Registered',
    ]);
    $onboarding = CandidateStatus::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $industry->id,
        'name' => 'Onboarding',
    ]);
    $candidate->statuses()->create(['candidate_status_id' => $registered->id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->callAction('changeStatus', data: ['candidate_status_id' => $onboarding->id])
        ->assertNotified('Status updated');

    $candidate->refresh();
    expect($candidate->statuses)->toHaveCount(1);
    expect($candidate->statuses->first()->candidate_status_id)->toBe($onboarding->id);

    $activity = $candidate->activities()->latest()->first();
    expect($activity->type)->toBe(ActivityType::StatusChange);
    expect($activity->note)->toBe('Status changed from "Registered" to "Onboarding"');
});
