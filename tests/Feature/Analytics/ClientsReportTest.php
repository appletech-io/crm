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

test('a non-admin cannot access the clients report', function () {
    $consultant = User::factory()->create();
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(ClientsReport::canAccess())->toBeFalse();
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

    $filledStatus = JobStatus::factory()->create([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'is_filled_status' => true,
    ]);

    Vacancy::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'job_title_id' => $jobTitle->id,
        'job_status_id' => $filledStatus->id,
        'filled_at' => now(),
        'placement_fee_percentage' => 15,
        'salary_min' => 20000,
        'salary_max' => 30000,
        'positions_available' => 1,
    ]);

    $component = Livewire::test(ClientsReport::class)->assertSuccessful();

    $stats = $component->instance()->stats();

    expect($stats['Clients active'])->toBe(1)
        ->and($stats['Booking revenue'])->toBe('£150.00')
        ->and($stats['Placements'])->toBe(1);
});
