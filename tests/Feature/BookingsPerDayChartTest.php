<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Widgets\BookingsPerDayChart;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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
    $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
});

function getChartData(?string $filter = null): array
{
    $component = Livewire::test(BookingsPerDayChart::class);

    if ($filter !== null) {
        $component->set('filter', $filter);
    }

    $reflection = new ReflectionClass($component->instance());
    $method = $reflection->getMethod('getData');
    $method->setAccessible(true);

    return $method->invoke($component->instance());
}

function addChartDayPeriod(Booking $booking, string $date, BookingDayPeriod $period = BookingDayPeriod::FullDay): void
{
    $booking->dayPeriods()->create([
        'company_id' => $booking->company_id,
        'date' => $date,
        'period' => $period,
    ]);
}

test('the widget renders successfully', function () {
    Livewire::test(BookingsPerDayChart::class)->assertSuccessful();
});

test('it defaults to the 2 week filter, returning one day label per day starting today', function () {
    $today = now()->startOfDay();
    $expectedDays = (int) $today->diffInDays($today->copy()->addWeeks(2)->subDay()) + 1;

    $data = getChartData();

    expect($data['labels'])->toHaveCount($expectedDays)
        ->and($data['labels'][0])->toBe($today->format('d M'))
        ->and($data['datasets'][0]['data'])->toHaveCount($expectedDays);
});

test('the time horizon filter changes how many days of data are returned', function () {
    $today = now()->startOfDay();

    $expectedWeeks = fn (int $weeks): int => (int) $today->diffInDays($today->copy()->addWeeks($weeks)->subDay()) + 1;
    $expectedMonth = (int) $today->diffInDays($today->copy()->addMonth()->subDay()) + 1;

    expect(getChartData(filter: '1_week')['labels'])->toHaveCount($expectedWeeks(1))
        ->and(getChartData(filter: '2_weeks')['labels'])->toHaveCount($expectedWeeks(2))
        ->and(getChartData(filter: '1_month')['labels'])->toHaveCount($expectedMonth);
});

test('it counts distinct bookings per day and excludes days outside the selected range', function () {
    $today = now()->startOfDay();

    $bookingA = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($bookingA, $today->toDateString());
    addChartDayPeriod($bookingA, $today->copy()->addDay()->toDateString());

    $bookingB = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($bookingB, $today->toDateString());

    $futureBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($futureBooking, $today->copy()->addDays(5)->toDateString());

    $tooFarBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($tooFarBooking, $today->copy()->addMonths(6)->toDateString());

    $data = getChartData();
    $counts = $data['datasets'][0]['data'];

    expect($counts[0])->toBe(2)
        ->and($counts[1])->toBe(1)
        ->and($counts[5])->toBe(1)
        ->and(array_sum($counts))->toBe(4);
});

test('it excludes day periods belonging to a soft-deleted booking', function () {
    $today = now()->startOfDay();

    $activeBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($activeBooking, $today->toDateString());

    $cancelledBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    addChartDayPeriod($cancelledBooking, $today->toDateString());
    $cancelledBooking->delete();

    $data = getChartData();

    expect($data['datasets'][0]['data'][0])->toBe(1);
});
