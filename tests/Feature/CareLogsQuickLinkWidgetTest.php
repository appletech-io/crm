<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Widgets\CareLogsQuickLink;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company->industries()->attach($this->industry->id, ['care_logging' => true]);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);
});

test('it shows a success message with no outstanding care logs', function () {
    Livewire::test(CareLogsQuickLink::class)
        ->assertSuccessful()
        ->assertSee('Outstanding Care Logs')
        ->assertSee('0')
        ->assertSee('All shifts logged');
});

test('it shows the outstanding count and a warning message once shifts need logging', function () {
    $client = Client::factory()->create(['company_id' => $this->company->id]);
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(CareLogsQuickLink::class)
        ->assertSuccessful()
        ->assertSee('1')
        ->assertSee('Shifts still needing a candidate log');
});
