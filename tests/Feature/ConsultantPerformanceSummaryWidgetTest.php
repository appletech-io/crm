<?php

use App\Ai\Agents\PerformanceSummaryAgent;
use App\Enums\BookingDayPeriod;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Widgets\ConsultantPerformanceSummary;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
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

test('more info links to the bookings index', function () {
    $url = Livewire::test(ConsultantPerformanceSummary::class)->instance()->moreInfoUrl();

    expect($url)->toBe(BookingResource::getUrl('index'));
});
