<?php

use App\Enums\ActivityType;
use App\Filament\Widgets\EducationConsultantKpiOverview;
use App\Models\CandidateActivity;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\Company;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);

    $this->company = $this->user->company;
});

function logCandidateActivity(User $user, EducationCandidate $candidate, ActivityType $type, string $createdAt): void
{
    $activity = CandidateActivity::create([
        'user_id' => $user->id,
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'type' => $type->value,
        'note' => 'note',
    ]);
    $activity->forceFill(['created_at' => $createdAt])->save();
}

function logClientActivity(User $user, Client $client, ActivityType $type, string $createdAt): void
{
    $activity = ClientActivity::create([
        'user_id' => $user->id,
        'model_type' => Client::class,
        'model_id' => $client->id,
        'type' => $type->value,
        'note' => 'note',
    ]);
    $activity->forceFill(['created_at' => $createdAt])->save();
}

test('it counts calls, meetings, and completed applications for the acting consultant this month', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultant->id]);
    $client = Client::factory()->create(['company_id' => $this->company->id]);

    $monthStart = Carbon::now()->startOfMonth();

    logCandidateActivity($consultant, $candidate, ActivityType::Call, $monthStart->copy()->addDays(2)->toDateTimeString());
    logClientActivity($consultant, $client, ActivityType::Call, $monthStart->copy()->addDays(3)->toDateTimeString());
    logCandidateActivity($consultant, $candidate, ActivityType::Meeting, $monthStart->copy()->addDays(4)->toDateTimeString());

    // Outside this month, should not count.
    logCandidateActivity($consultant, $candidate, ActivityType::Call, $monthStart->copy()->subMonth()->toDateTimeString());

    EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'completed',
        'completed_at' => $monthStart->copy()->addDays(5),
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", 1);

    $stats = Livewire::test(EducationConsultantKpiOverview::class)->instance()->monthStats();

    expect($stats['calls'])->toBe(2)
        ->and($stats['meetings'])->toBe(1)
        ->and($stats['completedApplications'])->toBe(1);
});

test('it counts completed applications from the previous month separately from this month', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultant->id]);

    $monthStart = Carbon::now()->startOfMonth();

    EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'completed',
        'completed_at' => $monthStart->copy()->addDays(5),
    ]);

    $previousMonthCandidateA = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultant->id]);
    EducationApplication::factory()->create([
        'education_candidate_id' => $previousMonthCandidateA->id,
        'status' => 'completed',
        'completed_at' => $monthStart->copy()->subMonth()->addDays(2),
    ]);

    $previousMonthCandidateB = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultant->id]);
    EducationApplication::factory()->create([
        'education_candidate_id' => $previousMonthCandidateB->id,
        'status' => 'completed',
        'completed_at' => $monthStart->copy()->subMonth()->addDays(10),
    ]);

    // Two months ago, should not count as "previous month".
    $twoMonthsAgoCandidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultant->id]);
    EducationApplication::factory()->create([
        'education_candidate_id' => $twoMonthsAgoCandidate->id,
        'status' => 'completed',
        'completed_at' => $monthStart->copy()->subMonths(2)->addDays(2),
    ]);

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", 1);

    $stats = Livewire::test(EducationConsultantKpiOverview::class)->instance()->monthStats();

    expect($stats['completedApplications'])->toBe(1)
        ->and($stats['previousMonthCompletedApplications'])->toBe(2);
});

test('a non admin consultant only sees their own activity', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantB->assignRole('consultant');

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    logCandidateActivity($consultantA, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());
    logCandidateActivity($consultantB, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());

    $this->actingAs($consultantA);
    Cache::put("user.{$consultantA->id}.active_industry", 'education');
    Cache::put("user.{$consultantA->id}.active_industry_id", 1);

    $stats = Livewire::test(EducationConsultantKpiOverview::class)->instance()->monthStats();

    expect($stats['calls'])->toBe(1);
});

test('an admin can filter the stats down to a single consultant, and sees all by default', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantB->assignRole('consultant');

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    logCandidateActivity($consultantA, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());
    logCandidateActivity($consultantB, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());

    $component = Livewire::test(EducationConsultantKpiOverview::class);
    expect($component->instance()->monthStats()['calls'])->toBe(2);

    $component->set('consultantId', $consultantA->id);
    expect($component->instance()->monthStats()['calls'])->toBe(1);
});

test('activity from another company is never counted even when viewing all consultants', function () {
    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $otherCompany->id]);

    logCandidateActivity($otherUser, $otherCandidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());

    $stats = Livewire::test(EducationConsultantKpiOverview::class)->instance()->monthStats();

    expect($stats['calls'])->toBe(0);
});
