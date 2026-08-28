<?php

use App\Ai\Agents\PerformanceSummaryAgent;
use App\Enums\BookingDayPeriod;
use App\Filament\Widgets\ConsultantPerformanceSummary;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ConsultantKpiTarget;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
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
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);
});

function createWidgetBooking(
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

test('it reports gross profit, days out, working candidates, clients booked, and rebook rate for this week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    createWidgetBooking($this->user, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);
    createWidgetBooking($this->user, $client, $candidate, $this->jobTitle, $monday->copy()->addWeek()->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    $stats = Livewire::test(ConsultantPerformanceSummary::class)->instance()->weekStats();

    expect($stats['gp'])->toBe(50.0)
        ->and($stats['daysPlaced'])->toBe(1)
        ->and($stats['candidates'])->toBe(1)
        ->and($stats['clients'])->toBe(1)
        ->and($stats['rebookRate'])->toBe(100.0);
});

test('a non admin consultant only sees their own bookings', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantB->assignRole('consultant');

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $monday = now()->startOfWeek(Carbon::MONDAY);

    createWidgetBooking($consultantA, $client, $candidate, $this->jobTitle, $monday->toDateString());
    createWidgetBooking($consultantB, $client, $candidate, $this->jobTitle, $monday->toDateString());

    $this->actingAs($consultantA);
    Cache::put("user.{$consultantA->id}.active_industry", 'education');
    Cache::put("user.{$consultantA->id}.active_industry_id", 1);

    $stats = Livewire::test(ConsultantPerformanceSummary::class)->instance()->weekStats();

    expect($stats['daysPlaced'])->toBe(1);
});

test('an admin sees every consultant by default and can filter to one', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');
    $consultantB = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantB->assignRole('consultant');

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $monday = now()->startOfWeek(Carbon::MONDAY);

    createWidgetBooking($consultantA, $client, $candidate, $this->jobTitle, $monday->toDateString());
    createWidgetBooking($consultantB, $client, $candidate, $this->jobTitle, $monday->toDateString());

    $component = Livewire::test(ConsultantPerformanceSummary::class);
    expect($component->instance()->weekStats()['daysPlaced'])->toBe(2);

    $component->set('consultantId', $consultantA->id);
    expect($component->instance()->weekStats()['daysPlaced'])->toBe(1);
});

test('the summary text comes from the performance agent and is cached', function () {
    PerformanceSummaryAgent::fake([
        'Solid week — gross profit is strong and next week is already fully rebooked.',
        'A completely different second response.',
    ]);

    $component = Livewire::test(ConsultantPerformanceSummary::class);

    expect($component->instance()->summaryText())
        ->toBe('Solid week — gross profit is strong and next week is already fully rebooked.');

    // A second call should reuse the cached value rather than consuming the
    // next fake response, which would prove it prompted the agent again.
    expect($component->instance()->summaryText())
        ->toBe('Solid week — gross profit is strong and next week is already fully rebooked.');
});

test('a failure generating the summary is reported and a fallback message is shown', function () {
    PerformanceSummaryAgent::fake(function () {
        throw new Exception('provider unavailable');
    });

    $text = Livewire::test(ConsultantPerformanceSummary::class)->instance()->summaryText();

    expect($text)->toBe('Performance summary is temporarily unavailable.');
});

test('the summary shows a loading placeholder until loadSummary runs, then shows the real text', function () {
    PerformanceSummaryAgent::fake(['This week went well.']);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->assertSet('summary', null)
        ->assertSee('Generating')
        ->assertDontSee('This week went well.')
        ->call('loadSummary')
        ->assertSet('summary', 'This week went well.')
        ->assertSee('This week went well.')
        ->assertDontSee('Generating');
});

test('switching consultant as an admin reloads the summary for the new consultant', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');

    PerformanceSummaryAgent::fake([
        'Summary for everyone.',
        'Summary for consultant A.',
    ]);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->call('loadSummary')
        ->assertSet('summary', 'Summary for everyone.')
        ->set('consultantId', $consultantA->id)
        ->assertSet('summary', 'Summary for consultant A.');
});

test('switching consultant dispatches an event so other dashboard widgets can follow the same selection', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');

    PerformanceSummaryAgent::fake(['Summary for consultant A.']);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->set('consultantId', $consultantA->id)
        ->assertDispatched('dashboard-consultant-changed', consultantId: $consultantA->id);
});

test('a non admin consultant always gets the monthly report link, pointing at their own id', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $this->actingAs($consultant);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", 1);

    $component = Livewire::test(ConsultantPerformanceSummary::class)->instance();

    expect($component->showMonthlyReportLink())->toBeTrue()
        ->and($component->monthlyReportUrl())->toContain((string) $consultant->id);
});

test('the cached summary does not leak between different companies viewing "All Consultants" for the same week', function () {
    PerformanceSummaryAgent::fake([
        'Summary for company A.',
        'Summary for company B.',
    ]);

    // This company (set up in beforeEach) generates its "All Consultants"
    // summary first.
    Livewire::test(ConsultantPerformanceSummary::class)
        ->call('loadSummary')
        ->assertSet('summary', 'Summary for company A.');

    // A completely different company/admin, viewing the same calendar week,
    // must get its own summary — not company A's cached text — since the
    // cache key must be scoped by company (and industry).
    $otherCompanyAdmin = User::factory()->create();
    $otherCompanyAdmin->assignRole('admin');
    $this->actingAs($otherCompanyAdmin);
    Cache::put("user.{$otherCompanyAdmin->id}.active_industry", 'education');
    Cache::put("user.{$otherCompanyAdmin->id}.active_industry_id", 1);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->call('loadSummary')
        ->assertSet('summary', 'Summary for company B.');
});

test('the cached summary does not leak between sectors of the same company for the same week', function () {
    PerformanceSummaryAgent::fake([
        'Summary for education.',
        'Summary for healthcare.',
    ]);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->call('loadSummary')
        ->assertSet('summary', 'Summary for education.');

    Cache::put("user.{$this->user->id}.active_industry", 'healthcare');
    Cache::put("user.{$this->user->id}.active_industry_id", 2);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->call('loadSummary')
        ->assertSet('summary', 'Summary for healthcare.');
});

test('a stat is colored green when this week\'s actual meets or exceeds the consultant\'s target', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');
    ConsultantKpiTarget::factory()->create([
        'user_id' => $consultant->id,
        'industry_id' => $industry->id,
        'gp_target' => 40,
    ]);

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $monday = now()->startOfWeek(Carbon::MONDAY);

    createWidgetBooking($consultant, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->set('consultantId', $consultant->id)
        ->assertSeeHtml('fi-color-success')
        ->assertSeeHtml('bg-success-50');
});

test('a stat is colored amber between 80% and 100% of the consultant\'s target', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');
    ConsultantKpiTarget::factory()->create([
        'user_id' => $consultant->id,
        'industry_id' => $industry->id,
        'gp_target' => 60,
    ]);

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $monday = now()->startOfWeek(Carbon::MONDAY);

    createWidgetBooking($consultant, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->set('consultantId', $consultant->id)
        ->assertSeeHtml('fi-color-warning')
        ->assertSeeHtml('bg-warning-50');
});

test('a stat is colored red below 80% of the consultant\'s target', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');
    ConsultantKpiTarget::factory()->create([
        'user_id' => $consultant->id,
        'industry_id' => $industry->id,
        'gp_target' => 1000,
    ]);

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $monday = now()->startOfWeek(Carbon::MONDAY);

    createWidgetBooking($consultant, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->set('consultantId', $consultant->id)
        ->assertSeeHtml('fi-color-danger')
        ->assertSeeHtml('bg-danger-50');
});

test('stats are never colored while viewing "All Consultants", even if the only consultant has a target', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');
    ConsultantKpiTarget::factory()->create([
        'user_id' => $consultant->id,
        'industry_id' => $industry->id,
        'gp_target' => 40,
    ]);

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $monday = now()->startOfWeek(Carbon::MONDAY);

    createWidgetBooking($consultant, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->assertDontSeeHtml('fi-color-success')
        ->assertDontSeeHtml('fi-color-warning')
        ->assertDontSeeHtml('fi-color-danger')
        ->assertDontSeeHtml('bg-success-50')
        ->assertDontSeeHtml('bg-warning-50')
        ->assertDontSeeHtml('bg-danger-50');
});

test('stats are uncolored when the selected consultant has no KPI target set for the active industry', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');

    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $monday = now()->startOfWeek(Carbon::MONDAY);

    createWidgetBooking($consultant, $client, $candidate, $this->jobTitle, $monday->toDateString(), [
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    Livewire::test(ConsultantPerformanceSummary::class)
        ->set('consultantId', $consultant->id)
        ->assertDontSeeHtml('fi-color-success')
        ->assertDontSeeHtml('fi-color-warning')
        ->assertDontSeeHtml('fi-color-danger')
        ->assertDontSeeHtml('bg-success-50')
        ->assertDontSeeHtml('bg-warning-50')
        ->assertDontSeeHtml('bg-danger-50');
});

test('an admin only gets the monthly report link once a specific consultant is selected', function () {
    $consultantA = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultantA->assignRole('consultant');

    $component = Livewire::test(ConsultantPerformanceSummary::class);

    expect($component->instance()->showMonthlyReportLink())->toBeFalse();

    $component->set('consultantId', $consultantA->id);

    expect($component->instance()->showMonthlyReportLink())->toBeTrue()
        ->and($component->instance()->monthlyReportUrl())->toContain((string) $consultantA->id);
});
