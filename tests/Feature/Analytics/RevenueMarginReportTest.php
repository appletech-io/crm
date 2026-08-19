<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Pages\Analytics\RevenueMarginReport;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('an admin can access the revenue report', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(RevenueMarginReport::canAccess())->toBeTrue();
});

test('a non-admin cannot access the revenue report', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(RevenueMarginReport::canAccess())->toBeFalse();
});

test('it renders successfully and totals a booking within the default period', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", $industry->id);

    $company = $admin->company;
    $jobTitle = JobTitle::factory()->create(['company_id' => $company->id]);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => now()->startOfMonth()->addDays(2),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $component = Livewire::test(RevenueMarginReport::class)->assertSuccessful();

    $stats = $component->instance()->stats();

    expect($stats['Bookings'])->toBe(1)
        ->and($stats['Revenue'])->toBe('£150.00')
        ->and($stats['Cost'])->toBe('£100.00')
        ->and($stats['Margin'])->toBe('£50.00');
});
