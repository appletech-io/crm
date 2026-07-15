<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Widgets\BookingsPerWeekChart;
use App\Models\EducationBooking;
use App\Models\EducationCandidate;
use App\Models\EducationClient;
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
    $this->client = EducationClient::factory()->create(['company_id' => $this->company->id]);
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
});

function getChartData(?string $filter = null): array
{
    $component = Livewire::test(BookingsPerWeekChart::class);

    if ($filter !== null) {
        $component->set('filter', $filter);
    }

    $reflection = new ReflectionClass($component->instance());
    $method = $reflection->getMethod('getData');
    $method->setAccessible(true);

    return $method->invoke($component->instance());
}

function addChartDayPeriod(EducationBooking $booking, string $date, BookingDayPeriod $period = BookingDayPeriod::FullDay): void
{
    $booking->dayPeriods()->create([
        'company_id' => $booking->company_id,
        'date' => $date,
        'period' => $period,
    ]);
}

test('the widget renders successfully', function () {
    Livewire::test(BookingsPerWeekChart::class)->assertSuccessful();
});

test('it defaults to the 3 month filter, returning 13 week labels starting with the current week', function () {
    $data = getChartData();

    expect($data['labels'])->toHaveCount(13)
        ->and($data['labels'][0])->toBe(now()->startOfWeek(Carbon::MONDAY)->format('d M'))
        ->and($data['datasets'][0]['data'])->toHaveCount(13);
});

test('the time horizon filter changes how many weeks of data are returned', function () {
    expect(getChartData(filter: '1_month')['labels'])->toHaveCount(4)
        ->and(getChartData(filter: '3_months')['labels'])->toHaveCount(13)
        ->and(getChartData(filter: '6_months')['labels'])->toHaveCount(26);
});

test('it counts distinct bookings per week and excludes weeks outside the selected range', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $bookingA = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($bookingA, $monday->toDateString());
    addChartDayPeriod($bookingA, $monday->copy()->addDay()->toDateString());

    $bookingB = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($bookingB, $monday->toDateString());

    $futureBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($futureBooking, $monday->copy()->addWeeks(3)->toDateString());

    $tooFarBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($tooFarBooking, $monday->copy()->addWeeks(20)->toDateString());

    $data = getChartData();
    $counts = $data['datasets'][0]['data'];

    expect($counts[0])->toBe(2)
        ->and($counts[3])->toBe(1)
        ->and(array_sum($counts))->toBe(3);
});

test('it excludes day periods belonging to a soft-deleted booking', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    $activeBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($activeBooking, $monday->toDateString());

    $cancelledBooking = EducationBooking::factory()->create([
        'company_id' => $this->company->id,
        'education_client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($cancelledBooking, $monday->toDateString());
    $cancelledBooking->delete();

    $data = getChartData();

    expect($data['datasets'][0]['data'][0])->toBe(1);
});
