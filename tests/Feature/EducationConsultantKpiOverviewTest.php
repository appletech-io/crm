<?php

use App\Enums\ActivityType;
use App\Filament\Resources\EducationCandidates\EducationCandidateResource;
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

test('it follows the dashboard-wide consultant selection dispatched by the performance summary widget', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantB->assignRole('consultant');

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    logCandidateActivity($consultantA, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());
    logCandidateActivity($consultantB, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());

    $component = Livewire::test(EducationConsultantKpiOverview::class)
        ->dispatch('dashboard-consultant-changed', consultantId: $consultantA->id)
        ->assertSet('consultantId', $consultantA->id);

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

test('clicking the calls stat lists the teams calls when admin is viewing all consultants', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Ada Lovelace']);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Bob Marley']);
    $consultantB->assignRole('consultant');

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);

    logCandidateActivity($consultantA, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());
    logCandidateActivity($consultantB, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());

    $activities = Livewire::test(EducationConsultantKpiOverview::class)
        ->instance()
        ->activitiesForModal(ActivityType::Call);

    expect($activities)->toHaveCount(2)
        ->and($activities->pluck('consultant'))->toContain('Ada Lovelace', 'Bob Marley');
});

test('the calls list only shows that consultants calls once one is selected', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Ada Lovelace']);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Bob Marley']);
    $consultantB->assignRole('consultant');

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    logCandidateActivity($consultantA, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());
    logCandidateActivity($consultantB, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());

    $activities = Livewire::test(EducationConsultantKpiOverview::class)
        ->set('consultantId', $consultantA->id)
        ->instance()
        ->activitiesForModal(ActivityType::Call);

    expect($activities)->toHaveCount(1)
        ->and($activities->first()['consultant'])->toBe('Ada Lovelace');
});

test('a non admin consultant only ever sees their own calls in the list', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Ada Lovelace']);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Bob Marley']);
    $consultantB->assignRole('consultant');

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    logCandidateActivity($consultantA, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());
    logCandidateActivity($consultantB, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());

    $this->actingAs($consultantA);
    Cache::put("user.{$consultantA->id}.active_industry", 'education');
    Cache::put("user.{$consultantA->id}.active_industry_id", 1);

    $activities = Livewire::test(EducationConsultantKpiOverview::class)
        ->instance()
        ->activitiesForModal(ActivityType::Call);

    expect($activities)->toHaveCount(1)
        ->and($activities->first()['consultant'])->toBe('Ada Lovelace');
});

test('the meetings list only shows meetings, not calls', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    logCandidateActivity($this->user, $candidate, ActivityType::Call, Carbon::now()->startOfMonth()->toDateTimeString());
    $meeting = CandidateActivity::create([
        'user_id' => $this->user->id,
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'type' => ActivityType::Meeting->value,
        'note' => 'Discussed availability for next term',
    ]);
    $meeting->forceFill(['created_at' => Carbon::now()->startOfMonth()])->save();

    $activities = Livewire::test(EducationConsultantKpiOverview::class)
        ->instance()
        ->activitiesForModal(ActivityType::Meeting);

    expect($activities)->toHaveCount(1)
        ->and($activities->first()['note'])->toBe('Discussed availability for next term');
});

test('clicking a stat mounts the drilldown action without error', function () {
    Livewire::test(EducationConsultantKpiOverview::class)
        ->mountAction('viewActivities', ['type' => 'call'])
        ->assertOk();
});

test('the completed applications list shows the whole teams candidates when admin is viewing all consultants', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Ada Lovelace']);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Bob Marley']);
    $consultantB->assignRole('consultant');

    $candidateA = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultantA->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);
    $candidateB = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultantB->id, 'first_name' => 'John', 'last_name' => 'Smith']);

    EducationApplication::factory()->create([
        'education_candidate_id' => $candidateA->id,
        'status' => 'completed',
        'completed_at' => Carbon::now()->startOfMonth()->addDay(),
    ]);
    EducationApplication::factory()->create([
        'education_candidate_id' => $candidateB->id,
        'status' => 'completed',
        'completed_at' => Carbon::now()->startOfMonth()->addDays(2),
    ]);

    $candidates = Livewire::test(EducationConsultantKpiOverview::class)
        ->instance()
        ->completedApplicationsForModal();

    expect($candidates)->toHaveCount(2)
        ->and($candidates->pluck('consultant'))->toContain('Ada Lovelace', 'Bob Marley')
        ->and($candidates->pluck('name'))->toContain('Jane Doe', 'John Smith');
});

test('the completed applications list narrows to one consultant once selected', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Ada Lovelace']);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id, 'name' => 'Bob Marley']);
    $consultantB->assignRole('consultant');

    $candidateA = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultantA->id]);
    $candidateB = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $consultantB->id]);

    EducationApplication::factory()->create([
        'education_candidate_id' => $candidateA->id,
        'status' => 'completed',
        'completed_at' => Carbon::now()->startOfMonth()->addDay(),
    ]);
    EducationApplication::factory()->create([
        'education_candidate_id' => $candidateB->id,
        'status' => 'completed',
        'completed_at' => Carbon::now()->startOfMonth()->addDays(2),
    ]);

    $candidates = Livewire::test(EducationConsultantKpiOverview::class)
        ->set('consultantId', $consultantA->id)
        ->instance()
        ->completedApplicationsForModal();

    expect($candidates)->toHaveCount(1)
        ->and($candidates->first()['consultant'])->toBe('Ada Lovelace');
});

test('the completed applications list links each candidate to their edit page', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id, 'consultant_id' => $this->user->id]);

    EducationApplication::factory()->create([
        'education_candidate_id' => $candidate->id,
        'status' => 'completed',
        'completed_at' => Carbon::now()->startOfMonth()->addDay(),
    ]);

    $candidates = Livewire::test(EducationConsultantKpiOverview::class)
        ->instance()
        ->completedApplicationsForModal();

    expect($candidates->first()['url'])->toBe(
        EducationCandidateResource::getUrl('edit', ['record' => $candidate])
    );
});

test('clicking the applications stat mounts the drilldown action without error', function () {
    Livewire::test(EducationConsultantKpiOverview::class)
        ->mountAction('viewCompletedApplications')
        ->assertOk();
});
