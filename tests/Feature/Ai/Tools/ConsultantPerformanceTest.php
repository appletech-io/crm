<?php

use App\Ai\Tools\ConsultantPerformance;
use App\Enums\BookingDayPeriod;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->industry = Industry::factory()->create();
    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);

    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->admin->company_id]);
});

function bookingForConsultantThisWeek(User $consultant, string $date, array $bookingAttributes = []): Booking
{
    $client = Client::factory()->create(['company_id' => $consultant->company_id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $consultant->company_id]);

    $booking = Booking::factory()->create(array_merge([
        'company_id' => $consultant->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'consultant_id' => $consultant->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ], $bookingAttributes));

    $booking->dayPeriods()->create([
        'company_id' => $consultant->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ]);

    return $booking;
}

test('a consultant sees their own bookings and margin for this week', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);
    $this->actingAs($consultant);

    bookingForConsultantThisWeek($consultant, now()->startOfWeek(Carbon::MONDAY)->toDateString());

    $result = (new ConsultantPerformance)->handle(new Request([]));

    expect($result)->toContain($consultant->name)
        ->and($result)->toContain('1 bookings')
        ->and($result)->toContain('revenue £150.00')
        ->and($result)->toContain('cost £100.00')
        ->and($result)->toContain('margin £50.00')
        ->and($result)->toContain('33.3% avg margin');
});

test('a non-admin cannot view another consultant\'s figures', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);
    $this->actingAs($consultant);

    $result = (new ConsultantPerformance)->handle(new Request(['consultant_name' => 'Anyone']));

    expect($result)->toBe('You can only see your own performance figures.');
});

test('an admin can view a named consultant\'s figures', function () {
    $this->actingAs($this->admin);

    $consultant = User::factory()->create(['company_id' => $this->admin->company_id, 'name' => 'Jordan Blake']);
    $consultant->assignRole('consultant');

    bookingForConsultantThisWeek($consultant, now()->startOfWeek(Carbon::MONDAY)->toDateString());

    $result = (new ConsultantPerformance)->handle(new Request(['consultant_name' => 'Jordan']));

    expect($result)->toContain('Jordan Blake')
        ->and($result)->toContain('1 bookings');
});

test('an admin searching for a non-matching consultant name gets a not-found message', function () {
    $this->actingAs($this->admin);

    $result = (new ConsultantPerformance)->handle(new Request(['consultant_name' => 'Nobody Here']));

    expect($result)->toBe('No consultant matching "Nobody Here" was found.');
});

test('bookings outside the current week are excluded from the totals', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');
    Cache::put("user.{$consultant->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $this->industry->id);
    $this->actingAs($consultant);

    bookingForConsultantThisWeek($consultant, now()->subWeeks(2)->toDateString());

    $result = (new ConsultantPerformance)->handle(new Request([]));

    expect($result)->toContain('0 bookings')
        ->and($result)->toContain('revenue £0.00');
});
