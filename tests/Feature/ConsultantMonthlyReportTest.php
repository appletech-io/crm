<?php

use App\Ai\Agents\BdmCallCoachingAgent;
use App\Ai\Agents\ConsultantMonthlyReportAgent;
use App\Enums\ActivityType;
use App\Enums\BookingDayPeriod;
use App\Filament\Pages\ConsultantMonthlyReport;
use App\Models\Booking;
use App\Models\CandidateActivity;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
    Cache::put("user.{$this->admin->id}.active_industry", 'education');
    Cache::put("user.{$this->admin->id}.active_industry_id", 1);

    $this->company = $this->admin->company;
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);

    $this->consultant = User::factory()->create(['company_id' => $this->company->id]);
    $this->consultant->assignRole('consultant');
});

function createReportBooking(
    User $consultant,
    Client $client,
    EducationCandidate $candidate,
    JobTitle $jobTitle,
    string $date,
    array $bookingAttributes = [],
): Booking {
    $booking = Booking::factory()->create(array_merge([
        'company_id' => $consultant->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'consultant_id' => $consultant->id,
    ], $bookingAttributes));

    $booking->dayPeriods()->create([
        'company_id' => $consultant->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ]);

    return $booking;
}

test('admins and consultants can access the page, but no one else', function () {
    expect(ConsultantMonthlyReport::canAccess())->toBeTrue();

    $this->actingAs($this->consultant);
    expect(ConsultantMonthlyReport::canAccess())->toBeTrue();

    $complianceOnly = User::factory()->create(['company_id' => $this->company->id]);
    $complianceOnly->assignRole('compliance');
    $this->actingAs($complianceOnly);
    expect(ConsultantMonthlyReport::canAccess())->toBeFalse();
});

test('a consultant always sees their own report regardless of the consultantId in the url', function () {
    $otherConsultant = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Someone Else']);
    $otherConsultant->assignRole('consultant');

    $this->actingAs($this->consultant);
    Cache::put("user.{$this->consultant->id}.active_industry", 'education');
    Cache::put("user.{$this->consultant->id}.active_industry_id", 1);

    $page = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $otherConsultant->id])
        ->instance();

    expect($page->consultantId)->toBe($this->consultant->id)
        ->and($page->consultant()->id)->toBe($this->consultant->id);
});

test('it 404s without a valid consultant id', function () {
    Livewire::test(ConsultantMonthlyReport::class)->assertStatus(404);
});

test('it 404s when the id does not belong to a consultant', function () {
    $notAConsultant = User::factory()->create(['company_id' => $this->company->id]);

    Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $notAConsultant->id])
        ->assertStatus(404);
});

test('periodStats totals gross profit and days out for the selected number of months', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    createReportBooking($this->consultant, $client, $candidate, $this->jobTitle, now()->subWeeks(2)->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    // Outside a 1 month window but inside 3.
    createReportBooking($this->consultant, $client, $candidate, $this->jobTitle, now()->subMonths(2)->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    $component = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id]);

    expect($component->instance()->periodStats()['daysPlaced'])->toBe(1);

    $component->call('setMonths', 3);

    expect($component->instance()->periodStats()['daysPlaced'])->toBe(2);
});

test('weeklyBreakdown covers every week in the period', function () {
    $component = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id]);

    $weeks = $component->instance()->weeklyBreakdown();

    expect($weeks->count())->toBeGreaterThanOrEqual(4);
});

test('activities merges candidate and client activities for the consultant within the period, most recent first', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
    $client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Riverside School']);
    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);

    $olderCall = CandidateActivity::create([
        'user_id' => $this->consultant->id,
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'type' => ActivityType::Call->value,
        'note' => 'Checked in',
    ]);
    $olderCall->forceFill(['created_at' => now()->subDays(3)])->save();

    $newerMeeting = ClientActivity::create([
        'user_id' => $this->consultant->id,
        'model_type' => Client::class,
        'model_id' => $client->id,
        'type' => ActivityType::Meeting->value,
        'note' => 'Booked a review',
    ]);
    $newerMeeting->forceFill(['created_at' => now()->subDay()])->save();

    // Different consultant — should not appear.
    CandidateActivity::create([
        'user_id' => $otherConsultant->id,
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'type' => ActivityType::Call->value,
        'note' => 'Not this consultant',
    ]);

    // Outside the period — should not appear.
    $tooOld = CandidateActivity::create([
        'user_id' => $this->consultant->id,
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'type' => ActivityType::Note->value,
        'note' => 'Ancient history',
    ]);
    $tooOld->forceFill(['created_at' => now()->subMonths(2)])->save();

    $activities = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])
        ->instance()
        ->activities();

    expect($activities)->toHaveCount(2)
        ->and($activities->first()['note'])->toBe('Booked a review')
        ->and($activities->first()['subject'])->toBe('Riverside School')
        ->and($activities->last()['note'])->toBe('Checked in')
        ->and($activities->last()['subject'])->toBe('Jane Doe');
});

test('activityCountsByType groups activities by their label', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    foreach (range(1, 2) as $i) {
        CandidateActivity::create([
            'user_id' => $this->consultant->id,
            'model_type' => EducationCandidate::class,
            'model_id' => $candidate->id,
            'type' => ActivityType::Call->value,
            'note' => "Call {$i}",
        ]);
    }

    $counts = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])
        ->instance()
        ->activityCountsByType();

    expect($counts)->toBe(['Call' => 2]);
});

test('the report shows a loading placeholder until loadSummary runs', function () {
    ConsultantMonthlyReportAgent::fake(['This period went well overall.']);

    Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])
        ->assertSet('summary', null)
        ->assertSee('Generating')
        ->call('loadSummary')
        ->assertSet('summary', 'This period went well overall.')
        ->assertSee('This period went well overall.');
});

test('the report summary is cached', function () {
    ConsultantMonthlyReportAgent::fake([
        'First report text.',
        'Second report text.',
    ]);

    $component = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])
        ->call('loadSummary');

    expect($component->get('summary'))->toBe('First report text.');

    $component->call('loadSummary');

    expect($component->get('summary'))->toBe('First report text.');
});

test('switching months reloads the report', function () {
    ConsultantMonthlyReportAgent::fake([
        'One month report.',
        'Three month report.',
    ]);

    Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])
        ->call('loadSummary')
        ->assertSet('summary', 'One month report.')
        ->call('setMonths', 3)
        ->assertSet('months', 3)
        ->assertSet('summary', 'Three month report.');
});

test('a failure generating the report falls back to a plain message', function () {
    ConsultantMonthlyReportAgent::fake(function () {
        throw new Exception('provider unavailable');
    });

    $page = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])->instance();
    $page->loadSummary();

    expect($page->summary)->toBe('Performance report is temporarily unavailable.');
});

test('bdmCallActivities only includes BDM calls and calls to clients, for this consultant, within the period', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Riverside School']);
    $otherConsultant = User::factory()->create(['company_id' => $this->company->id]);

    $bdmCall = ClientActivity::create([
        'user_id' => $this->consultant->id,
        'model_type' => Client::class,
        'model_id' => $client->id,
        'type' => ActivityType::BdmCall->value,
        'note' => 'Pitched a new framework agreement',
    ]);
    $bdmCall->forceFill(['created_at' => now()->subDay()])->save();

    ClientActivity::create([
        'user_id' => $this->consultant->id,
        'model_type' => Client::class,
        'model_id' => $client->id,
        'type' => ActivityType::Call->value,
        'note' => 'Quick catch-up call',
    ]);

    // Wrong type — not a call.
    ClientActivity::create([
        'user_id' => $this->consultant->id,
        'model_type' => Client::class,
        'model_id' => $client->id,
        'type' => ActivityType::Meeting->value,
        'note' => 'On-site meeting',
    ]);

    // Wrong consultant.
    ClientActivity::create([
        'user_id' => $otherConsultant->id,
        'model_type' => Client::class,
        'model_id' => $client->id,
        'type' => ActivityType::Call->value,
        'note' => 'Not this consultant',
    ]);

    // A candidate call should never count — this is about client BD technique.
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    CandidateActivity::create([
        'user_id' => $this->consultant->id,
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'type' => ActivityType::Call->value,
        'note' => 'Candidate call, not a BDM call',
    ]);

    $calls = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])
        ->instance()
        ->bdmCallActivities();

    expect($calls)->toHaveCount(2)
        ->and($calls->pluck('note'))->toContain('Pitched a new framework agreement', 'Quick catch-up call')
        ->and($calls->first()['subject'])->toBe('Riverside School');
});

function logBdmCall(User $consultant, Client $client, string $note = 'Discussed upcoming vacancies'): void
{
    ClientActivity::create([
        'user_id' => $consultant->id,
        'model_type' => Client::class,
        'model_id' => $client->id,
        'type' => ActivityType::BdmCall->value,
        'note' => $note,
    ]);
}

test('the BDM coaching tab shows a loading placeholder until loadBdmSummary runs', function () {
    logBdmCall($this->consultant, Client::factory()->create(['company_id' => $this->company->id]));
    BdmCallCoachingAgent::fake(['Good detail on next steps, but log more calls overall.']);

    Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])
        ->call('setTab', 'bdm-calls')
        ->assertSet('bdmSummary', null)
        ->assertSee('Reviewing')
        ->call('loadBdmSummary')
        ->assertSet('bdmSummary', 'Good detail on next steps, but log more calls overall.')
        ->assertSee('Good detail on next steps, but log more calls overall.');
});

test('the BDM coaching summary is cached', function () {
    logBdmCall($this->consultant, Client::factory()->create(['company_id' => $this->company->id]));
    BdmCallCoachingAgent::fake([
        'First coaching note.',
        'Second coaching note.',
    ]);

    $page = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id]);
    $page->call('loadBdmSummary');

    expect($page->get('bdmSummary'))->toBe('First coaching note.');

    $page->call('loadBdmSummary');

    expect($page->get('bdmSummary'))->toBe('First coaching note.');
});

test('switching months reloads the BDM coaching summary too', function () {
    logBdmCall($this->consultant, Client::factory()->create(['company_id' => $this->company->id]));
    BdmCallCoachingAgent::fake([
        'One month coaching.',
        'Three month coaching.',
    ]);

    Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])
        ->call('loadBdmSummary')
        ->assertSet('bdmSummary', 'One month coaching.')
        ->call('setMonths', 3)
        ->assertSet('bdmSummary', 'Three month coaching.');
});

test('a failure generating BDM coaching falls back to a plain message', function () {
    logBdmCall($this->consultant, Client::factory()->create(['company_id' => $this->company->id]));
    BdmCallCoachingAgent::fake(function () {
        throw new Exception('provider unavailable');
    });

    $page = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])->instance();
    $page->loadBdmSummary();

    expect($page->bdmSummary)->toBe('BDM call coaching is temporarily unavailable.');
});

test('BDM coaching says plainly when there is nothing to coach on, rather than inventing feedback', function () {
    $page = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id])->instance();
    $page->loadBdmSummary();

    expect($page->bdmSummary)->toContain('nothing to coach on');
});

test('setTab only accepts the two known tabs', function () {
    $component = Livewire::test(ConsultantMonthlyReport::class, ['consultantId' => $this->consultant->id]);

    $component->call('setTab', 'bdm-calls')->assertSet('activeTab', 'bdm-calls');
    $component->call('setTab', 'not-a-real-tab')->assertSet('activeTab', 'summary');
});
