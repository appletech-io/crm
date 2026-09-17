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

test('a site admin cannot access the revenue report', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    expect(RevenueMarginReport::canAccess())->toBeFalse();
});

test('a consultant can access the revenue report, seeing only their own data', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(RevenueMarginReport::canAccess())->toBeTrue();
});

test('a resourcer cannot access the revenue report', function () {
    $resourcer = User::factory()->create();
    $resourcer->assignRole('resourcer');
    $this->actingAs($resourcer);

    expect(RevenueMarginReport::canAccess())->toBeFalse();
});

test('a consultant only sees their own bookings, regardless of the consultant filter', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$consultant->id}.active_industry", 'education');
    Cache::put("user.{$consultant->id}.active_industry_id", $industry->id);

    $company = $consultant->company;
    $otherConsultant = User::factory()->create(['company_id' => $company->id]);
    $otherConsultant->assignRole('consultant');
    $jobTitle = JobTitle::factory()->create(['company_id' => $company->id]);
    $client = Client::factory()->create(['company_id' => $company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $ownBooking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'consultant_id' => $consultant->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ]);
    $ownBooking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => now()->startOfMonth()->addDays(2),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $othersBooking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'consultant_id' => $otherConsultant->id,
        'day_rate' => 200,
        'day_charge_rate' => 300,
    ]);
    $othersBooking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => now()->startOfMonth()->addDays(3),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $component = Livewire::test(RevenueMarginReport::class)->assertSuccessful();

    $stats = $component->instance()->stats();

    // Only the consultant's own booking (£150), never the other
    // consultant's (£300) — even though no consultant filter was applied,
    // since the picker is hidden from them entirely.
    expect($stats['Bookings'])->toBe(1)
        ->and($stats['Revenue'])->toBe('£150.00');
});

test('the consultant filter is hidden from a consultant but visible to an admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(RevenueMarginReport::class)
        ->assertTableFilterVisible('consultant_id');

    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    Livewire::test(RevenueMarginReport::class)
        ->assertTableFilterHidden('consultant_id');
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
        ->and($stats['Cost'])->toBe('£115.00')
        ->and($stats['Margin'])->toBe('£35.00');
});
