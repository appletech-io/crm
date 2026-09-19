<?php

use App\Enums\BookingDayPeriod;
use App\Enums\Healthcare\Wellbeing;
use App\Filament\Pages\CareLogsOverview;
use App\Models\Booking;
use App\Models\CareLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyIndustry;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company->industries()->attach($this->industry->id);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Oakwood Care Home']);
});

function enableOverviewCareLogging(): void
{
    CompanyIndustry::where('company_id', test()->company->id)
        ->where('industry_id', test()->industry->id)
        ->update(['care_logging' => true]);
}

test('a consultant cannot access the Care Logs overview when the flag is off', function () {
    expect(CareLogsOverview::canAccess())->toBeFalse();
});

test('a consultant can access the Care Logs overview once the flag is on', function () {
    enableOverviewCareLogging();

    expect(CareLogsOverview::canAccess())->toBeTrue();

    Livewire::test(CareLogsOverview::class)->assertSuccessful();
});

test('a compliance-only user cannot access the Care Logs overview even with the flag on', function () {
    enableOverviewCareLogging();

    $compliance = User::factory()->create(['company_id' => $this->company->id]);
    $compliance->assignRole('compliance');
    $this->actingAs($compliance);

    Cache::put("user.{$compliance->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$compliance->id}.active_industry_id", $this->industry->id);

    expect(CareLogsOverview::canAccess())->toBeFalse();
});

test('it lists shifts across every booking and candidate, with candidate, client, and status', function () {
    enableOverviewCareLogging();

    $candidateA = HealthcareCandidate::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);
    $candidateB = HealthcareCandidate::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Sam', 'last_name' => 'Price']);

    $bookingA = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidateA->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);
    $bookingB = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidateB->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    $loggedDay = $bookingA->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(2)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $loggedDay->id,
        'wellbeing' => Wellbeing::Good->value,
        'care_provided' => 'Supported with meals and mobility.',
    ]);

    $bookingB->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::WakingNight,
    ]);

    Livewire::test(CareLogsOverview::class)
        ->assertSee('Jane Doe')
        ->assertSee('Sam Price')
        ->assertSee('Oakwood Care Home')
        ->assertSee('Logged')
        ->assertSee('Outstanding');
});

test('the status filter isolates outstanding shifts from logged ones', function () {
    enableOverviewCareLogging();

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    $loggedDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(2)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $loggedDay->id,
    ]);

    $outstandingDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::WakingNight,
    ]);

    Livewire::test(CareLogsOverview::class)
        ->filterTable('status', 'outstanding')
        ->assertCanSeeTableRecords([$outstandingDay])
        ->assertCanNotSeeTableRecords([$loggedDay]);

    Livewire::test(CareLogsOverview::class)
        ->filterTable('status', 'logged')
        ->assertCanSeeTableRecords([$loggedDay])
        ->assertCanNotSeeTableRecords([$outstandingDay]);
});

test('the View Log action shows the log details and is hidden for unlogged shifts', function () {
    enableOverviewCareLogging();

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    $loggedDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(2)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $loggedDay->id,
        'wellbeing' => Wellbeing::Poor->value,
        'care_provided' => 'Assisted with a fall recovery.',
        'incidents_occurred' => true,
        'incident_details' => 'Candidate fell in the bathroom, no injury.',
        'handover_notes' => 'Keep an eye on mobility tomorrow.',
    ]);

    $outstandingDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::WakingNight,
    ]);

    Livewire::test(CareLogsOverview::class)
        ->assertTableActionVisible('viewLog', $loggedDay)
        ->assertTableActionHidden('viewLog', $outstandingDay)
        ->mountTableAction('viewLog', $loggedDay)
        ->assertSee('Assisted with a fall recovery.')
        ->assertSee('Candidate fell in the bathroom, no injury.')
        ->assertSee('Keep an eye on mobility tomorrow.');
});

test('outstandingCount and the nav badge only count non-cancelled, past-dated, unlogged shifts', function () {
    enableOverviewCareLogging();

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    // Outstanding: past, not cancelled, unlogged.
    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    // Not outstanding: cancelled.
    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
        'cancelled_at' => now(),
    ]);

    // Not outstanding: future.
    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->addDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    // Not outstanding: already logged.
    $loggedDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(3)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create(['company_id' => $this->company->id, 'booking_day_id' => $loggedDay->id]);

    expect(CareLogsOverview::outstandingCount())->toBe(1)
        ->and(CareLogsOverview::getNavigationBadge())->toBe('1');
});

test('the Issue column badges a flagged log, a clean log, and an unlogged shift differently', function () {
    enableOverviewCareLogging();

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    $flaggedDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(3)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $flaggedDay->id,
        'incidents_occurred' => true,
        'incident_details' => 'Candidate reported a fall, no injury.',
    ]);

    $cleanDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(2)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $cleanDay->id,
        'incidents_occurred' => false,
    ]);

    Livewire::test(CareLogsOverview::class)
        ->assertSee('Flagged')
        ->assertSee('None');
});

test('the Issue Raised filter isolates flagged logs from clean ones', function () {
    enableOverviewCareLogging();

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    $flaggedDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(3)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $flaggedDay->id,
        'incidents_occurred' => true,
        'incident_details' => 'Candidate reported a fall, no injury.',
    ]);

    $cleanDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(2)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $cleanDay->id,
        'incidents_occurred' => false,
    ]);

    Livewire::test(CareLogsOverview::class)
        ->filterTable('issue_flagged', true)
        ->assertCanSeeTableRecords([$flaggedDay])
        ->assertCanNotSeeTableRecords([$cleanDay]);

    Livewire::test(CareLogsOverview::class)
        ->filterTable('issue_flagged', false)
        ->assertCanSeeTableRecords([$cleanDay])
        ->assertCanNotSeeTableRecords([$flaggedDay]);
});

test('flaggedIssuesCount only counts logged shifts with an incident, not outstanding or clean ones', function () {
    enableOverviewCareLogging();

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    // Flagged.
    $flaggedDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(3)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $flaggedDay->id,
        'incidents_occurred' => true,
        'incident_details' => 'Candidate reported a fall, no injury.',
    ]);

    // Logged, but clean — not counted.
    $cleanDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDays(2)->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $cleanDay->id,
        'incidents_occurred' => false,
    ]);

    // Outstanding, unlogged — not counted (nothing to flag yet).
    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    expect(CareLogsOverview::flaggedIssuesCount())->toBe(1);
});

test('the View Log modal badges an issue as Flagged in red and shows its details', function () {
    enableOverviewCareLogging();

    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    $flaggedDay = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
    CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $flaggedDay->id,
        'incidents_occurred' => true,
        'incident_details' => 'Candidate reported a fall, no injury.',
    ]);

    Livewire::test(CareLogsOverview::class)
        ->mountTableAction('viewLog', $flaggedDay)
        ->assertSee('Issue Raised')
        ->assertSee('Flagged')
        ->assertSee('Candidate reported a fall, no injury.');
});

test('a different company\'s shifts never appear on this overview', function () {
    enableOverviewCareLogging();

    $otherCompany = Company::factory()->create();
    $otherIndustry = Industry::factory()->create(['slug' => 'healthcare']);
    $otherCompany->industries()->attach($otherIndustry->id, ['care_logging' => true]);

    $otherCandidate = HealthcareCandidate::factory()->create(['company_id' => $otherCompany->id]);
    $otherClient = Client::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Rival Agency Client']);
    $otherBooking = Booking::factory()->create([
        'company_id' => $otherCompany->id,
        'client_id' => $otherClient->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);
    $otherBooking->dayPeriods()->create([
        'company_id' => $otherCompany->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(CareLogsOverview::class)
        ->assertDontSee('Rival Agency Client');
});
