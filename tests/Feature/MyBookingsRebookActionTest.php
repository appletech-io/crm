<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Filament\Client\Pages\MyBookings;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->consultant = User::factory()->create(['company_id' => $this->company->id]);
    $this->consultant->assignRole('consultant');

    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'consultant_id' => $this->consultant->id,
    ]);

    $this->contact = ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
    ]);

    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'client_contact_id' => $this->contact->id,
    ]);
    $this->user->assignRole('client');
    $this->actingAs($this->user);

    $this->candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->existingBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
    ]);

    $this->day = $this->existingBooking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
        'payroll_confirmation_sent_at' => now(),
    ]);
});

test('rebooking from an existing booking day creates a new requested booking for the same candidate', function () {
    Livewire::test(MyBookings::class)
        ->callTableAction('rebook', $this->day, data: [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'day_periods' => [
                ['date' => '2026-09-01', 'period' => 'full_day'],
                ['date' => '2026-09-02', 'period' => 'am'],
            ],
            'notes' => 'Same candidate again please.',
        ])
        ->assertNotified('Booking requested — your consultant will confirm the details shortly.');

    $newBooking = Booking::where('client_id', $this->client->id)
        ->where('id', '!=', $this->existingBooking->id)
        ->first();

    expect($newBooking)->not->toBeNull()
        ->and($newBooking->status)->toBe(BookingStatus::Requested)
        ->and($newBooking->candidate_type)->toBe(EducationCandidate::class)
        ->and($newBooking->candidate_id)->toBe($this->candidate->id)
        ->and($newBooking->consultant_id)->toBe($this->consultant->id)
        ->and($newBooking->notes)->toBe('Same candidate again please.')
        ->and($newBooking->dayPeriods()->count())->toBe(2);
});

test('rebooking does not affect the original booking', function () {
    $originalStatus = $this->existingBooking->status;

    Livewire::test(MyBookings::class)
        ->callTableAction('rebook', $this->day, data: [
            'start_date' => '2026-09-01',
            'day_periods' => [
                ['date' => '2026-09-01', 'period' => 'full_day'],
            ],
        ]);

    expect($this->existingBooking->fresh()->status)->toBe($originalStatus)
        ->and(Booking::where('client_id', $this->client->id)->count())->toBe(2);
});
