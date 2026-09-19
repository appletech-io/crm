<?php

use App\Enums\BookingDayPeriod;
use App\Enums\Healthcare\Wellbeing;
use App\Filament\EducationCandidate\Pages\CareLogs;
use App\Models\Booking;
use App\Models\CareLog;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->industry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company = Company::factory()->create();
    $this->company->industries()->attach($this->industry->id);

    $this->candidate = HealthcareCandidate::factory()->create(['company_id' => $this->company->id]);
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);
    $this->user->assignRole('candidate');

    $this->client = Client::factory()->create(['company_id' => $this->company->id, 'name' => 'Oakwood Care Home']);
    $this->location = ClientLocation::factory()->create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'name' => 'Main Site']);
});

function enableCareLogging(): void
{
    \App\Models\CompanyIndustry::where('company_id', test()->company->id)
        ->where('industry_id', test()->industry->id)
        ->update(['care_logging' => true]);
}

function makeCareLogBooking(): Booking
{
    return Booking::factory()->create([
        'company_id' => test()->company->id,
        'client_id' => test()->client->id,
        'location_id' => test()->location->id,
        'candidate_id' => test()->candidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);
}

test('a Healthcare candidate cannot access Care Logs when the flag is off', function () {
    $this->actingAs($this->user);

    expect(CareLogs::canAccess())->toBeFalse();
});

test('a Healthcare candidate can access Care Logs once the flag is on', function () {
    enableCareLogging();
    $this->actingAs($this->user);

    expect(CareLogs::canAccess())->toBeTrue();

    Livewire::test(CareLogs::class)->assertSuccessful();
});

test('an Education candidate cannot access Care Logs even when the flag is on for their industry', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($educationIndustry->id, ['care_logging' => true]);

    $educationCandidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $educationUser = User::factory()->create([
        'company_id' => $this->company->id,
        'candidate_id' => $educationCandidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $educationUser->assignRole('candidate');
    $this->actingAs($educationUser);

    expect(CareLogs::canAccess())->toBeFalse();
});

test('the shift list shows client, location, date, times and booking type', function () {
    enableCareLogging();
    $this->actingAs($this->user);

    $booking = makeCareLogBooking();
    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::WakingNight,
        'time_from' => '22:00',
        'time_to' => '07:00',
    ]);

    Livewire::test(CareLogs::class)
        ->assertSee('Oakwood Care Home')
        ->assertSee('Main Site')
        ->assertSee('22:00 - 07:00')
        ->assertSee('Waking Night');
});

test('a past unlogged shift is highlighted as Outstanding and counted on the nav badge', function () {
    enableCareLogging();
    $this->actingAs($this->user);

    $booking = makeCareLogBooking();
    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(CareLogs::class)->assertSee('Outstanding');

    expect(CareLogs::getNavigationBadge())->toBe('1');
});

test('a future shift is not outstanding and has no Log Activity action', function () {
    enableCareLogging();
    $this->actingAs($this->user);

    $booking = makeCareLogBooking();
    $day = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->addDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(CareLogs::class)->assertSee('Not yet due');

    expect(CareLogs::getNavigationBadge())->toBeNull()
        ->and($day->needsCareLog())->toBeFalse();
});

test('a cancelled shift is never outstanding', function () {
    enableCareLogging();
    $this->actingAs($this->user);

    $booking = makeCareLogBooking();
    $day = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
        'cancelled_at' => now(),
    ]);

    Livewire::test(CareLogs::class);

    expect(CareLogs::getNavigationBadge())->toBeNull()
        ->and($day->needsCareLog())->toBeFalse();
});

test('a candidate can log activity for a past shift', function () {
    enableCareLogging();
    $this->actingAs($this->user);

    $booking = makeCareLogBooking();
    $day = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(CareLogs::class)
        ->callTableAction('logActivity', $day, data: [
            'wellbeing' => Wellbeing::Good->value,
            'care_provided' => 'Assisted with morning routine and meals.',
            'incidents_occurred' => true,
            'incident_details' => 'Minor trip in the hallway, no injury.',
            'medication_administered' => false,
            'handover_notes' => 'Nothing further to add.',
        ])
        ->assertHasNoTableActionErrors();

    $careLog = CareLog::where('booking_day_id', $day->id)->first();

    expect($careLog)->not->toBeNull()
        ->and($careLog->wellbeing)->toBe(Wellbeing::Good)
        ->and($careLog->care_provided)->toBe('Assisted with morning routine and meals.')
        ->and($careLog->incidents_occurred)->toBeTrue()
        ->and($careLog->incident_details)->toBe('Minor trip in the hallway, no injury.')
        ->and($careLog->medication_administered)->toBeFalse()
        ->and($careLog->submitted_at)->not->toBeNull();
});

test('incident/medication details are required once their toggle is on', function () {
    enableCareLogging();
    $this->actingAs($this->user);

    $booking = makeCareLogBooking();
    $day = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    Livewire::test(CareLogs::class)
        ->callTableAction('logActivity', $day, data: [
            'wellbeing' => Wellbeing::Fair->value,
            'care_provided' => 'Supported with personal care.',
            'incidents_occurred' => true,
            'incident_details' => '',
        ])
        ->assertHasTableActionErrors(['incident_details']);

    expect(CareLog::where('booking_day_id', $day->id)->exists())->toBeFalse();
});

test('editing an existing log keeps its original submitted_at', function () {
    enableCareLogging();
    $this->actingAs($this->user);

    $booking = makeCareLogBooking();
    $day = $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->subDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $originalSubmittedAt = now()->subHours(5);

    $careLog = CareLog::factory()->create([
        'company_id' => $this->company->id,
        'booking_day_id' => $day->id,
        'wellbeing' => Wellbeing::Good->value,
        'care_provided' => 'Original note.',
        'submitted_at' => $originalSubmittedAt,
    ]);

    Livewire::test(CareLogs::class)
        ->callTableAction('logActivity', $day, data: [
            'wellbeing' => Wellbeing::Poor->value,
            'care_provided' => 'Updated note after a follow-up.',
            'incidents_occurred' => false,
            'medication_administered' => false,
        ])
        ->assertHasNoTableActionErrors();

    expect(CareLog::where('booking_day_id', $day->id)->count())->toBe(1);

    $careLog->refresh();

    expect($careLog->care_provided)->toBe('Updated note after a follow-up.')
        ->and($careLog->wellbeing)->toBe(Wellbeing::Poor)
        ->and($careLog->submitted_at->equalTo($originalSubmittedAt))->toBeTrue();
});
