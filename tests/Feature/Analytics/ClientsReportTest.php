<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Pages\Analytics\ClientsReport;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyPlacement;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('an admin can access the clients report', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    expect(ClientsReport::canAccess())->toBeTrue();
});

test('a site admin cannot access the clients report', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    expect(ClientsReport::canAccess())->toBeFalse();
});

test('a consultant can access the clients report, seeing only their own data', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(ClientsReport::canAccess())->toBeTrue();
});

test('a resourcer cannot access the clients report', function () {
    $resourcer = User::factory()->create();
    $resourcer->assignRole('resourcer');
    $this->actingAs($resourcer);

    expect(ClientsReport::canAccess())->toBeFalse();
});

test('a consultant only sees their own booking revenue, and the consultant filter is hidden', function () {
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
    $client = Client::factory()->create(['company_id' => $company->id, 'industry_id' => $industry->id]);
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

    $component = Livewire::test(ClientsReport::class)
        ->assertSuccessful()
        ->assertTableFilterHidden('consultant_id');

    $stats = $component->instance()->stats();

    expect($stats['Booking revenue'])->toBe('£150.00');

    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", $industry->id);

    Livewire::test(ClientsReport::class)
        ->assertTableFilterVisible('consultant_id');
});

test('it renders successfully and combines booking revenue with placements per client', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", $industry->id);

    $company = $admin->company;
    $jobTitle = JobTitle::factory()->create(['company_id' => $company->id]);
    $client = Client::factory()->create(['company_id' => $company->id, 'industry_id' => $industry->id, 'name' => 'Acme Ltd']);
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

    $openStatus = JobStatus::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'is_filled_status' => false,
    ]);

    $vacancy = Vacancy::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $openStatus->id,
        'placement_fee_percentage' => 15,
        'salary_min' => 20000,
        'salary_max' => 30000,
        'positions_available' => 1,
    ]);

    VacancyPlacement::factory()->create([
        'vacancy_id' => $vacancy->id,
        'candidate_type' => EducationCandidate::class,
        'candidate_id' => EducationCandidate::factory()->create(['company_id' => $company->id])->id,
        'actual_salary' => 25000,
        'placed_at' => now(),
    ]);

    $component = Livewire::test(ClientsReport::class)->assertSuccessful();

    $stats = $component->instance()->stats();

    expect($stats['Clients active'])->toBe(1)
        ->and($stats['Booking revenue'])->toBe('£150.00')
        ->and($stats['Placements'])->toBe(1);
});

test('booking stats and columns are omitted when the active industry has bookings switched off', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $industry = Industry::factory()->create(['slug' => 'education']);
    $admin->company->industries()->attach($industry->id, ['uses_bookings' => false]);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", $industry->id);

    $component = Livewire::test(ClientsReport::class)->assertSuccessful();

    $stats = $component->instance()->stats();

    expect($stats)->not->toHaveKey('Booking revenue')
        ->and($stats)->not->toHaveKey('Booking margin')
        ->and($stats)->toHaveKey('Placements');
});

test('placement stats and columns are omitted when the active industry has perm switched off', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $industry = Industry::factory()->create(['slug' => 'education']);
    $admin->company->industries()->attach($industry->id, ['uses_perm' => false]);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", $industry->id);

    $component = Livewire::test(ClientsReport::class)->assertSuccessful();

    $stats = $component->instance()->stats();

    expect($stats)->not->toHaveKey('Placements')
        ->and($stats)->not->toHaveKey('Placement value')
        ->and($stats)->toHaveKey('Booking revenue');
});
