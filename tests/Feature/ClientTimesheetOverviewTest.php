<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Widgets\ClientTimesheetOverview;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
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
    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'industry_id' => 1]);
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
});

function overviewBookingWithDay(Client $client, EducationCandidate $candidate, JobTitle $jobTitle, string $date): array
{
    $booking = Booking::factory()->create([
        'company_id' => $client->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
    ]);

    $day = $booking->dayPeriods()->create([
        'company_id' => $client->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ]);

    return [$booking, $day];
}

test('the widget renders for a client', function () {
    Livewire::test(ClientTimesheetOverview::class, ['record' => $this->client])
        ->assertSuccessful();
});

test('it shows days within the current period and excludes days outside it', function () {
    [$booking, $inPeriodDay] = overviewBookingWithDay($this->client, $this->candidate, $this->jobTitle, now()->toDateString());
    [, $outOfPeriodDay] = overviewBookingWithDay($this->client, $this->candidate, $this->jobTitle, now()->addMonths(2)->toDateString());

    Livewire::test(ClientTimesheetOverview::class, ['record' => $this->client])
        ->assertCanSeeTableRecords([$inPeriodDay])
        ->assertCanNotSeeTableRecords([$outOfPeriodDay]);
});

test('the row links through to the booking edit page', function () {
    [$booking, $day] = overviewBookingWithDay($this->client, $this->candidate, $this->jobTitle, now()->toDateString());

    $html = Livewire::test(ClientTimesheetOverview::class, ['record' => $this->client])->html();

    expect($html)->toContain(e(BookingResource::getUrl('edit', ['record' => $booking])));
});

test('navigating to the next period changes which days are visible', function () {
    [, $currentDay] = overviewBookingWithDay($this->client, $this->candidate, $this->jobTitle, now()->toDateString());

    $currentPeriod = TimesheetPeriod::current($this->company);
    $nextPeriod = TimesheetPeriod::next($this->company, $currentPeriod['start']);

    [, $nextDay] = overviewBookingWithDay($this->client, $this->candidate, $this->jobTitle, $nextPeriod['start']->toDateString());

    Livewire::test(ClientTimesheetOverview::class, ['record' => $this->client])
        ->assertCanSeeTableRecords([$currentDay])
        ->call('goToNextPeriod')
        ->assertCanSeeTableRecords([$nextDay])
        ->assertCanNotSeeTableRecords([$currentDay]);
});

test('the timesheets tab renders on the client edit page', function () {
    Livewire::test(EditClient::class, ['record' => $this->client->id])
        ->assertSuccessful()
        ->assertSee('Timesheets');
});
