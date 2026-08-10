<?php

use App\Actions\Clients\EnsureClientCandidatePool;
use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Filament\Client\Pages\MyCandidates;
use App\Jobs\SendBookingConfirmationEmail;
use App\Jobs\SendClientBookingConfirmationEmail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    // Faked before any factory runs below: Client::factory() triggers
    // ClientObserver's real (synchronous, QUEUE_CONNECTION=sync in tests)
    // GeocodeClient dispatch, which makes a live HTTP call if not faked first.
    Bus::fake();

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

    $pool = EnsureClientCandidatePool::run($this->client);
    $pool->candidatesOfType(EducationCandidate::class)->attach($this->candidate->id);
});

test('booking a pooled candidate creates a requested booking with no job title or rates', function () {
    Livewire::test(MyCandidates::class)
        ->callTableAction('book', $this->candidate, data: [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'day_periods' => [
                ['date' => '2026-09-01', 'period' => 'full_day'],
                ['date' => '2026-09-02', 'period' => 'am'],
            ],
            'notes' => 'Needs someone for the whole week please.',
        ])
        ->assertNotified('Booking requested — your consultant will confirm the details shortly.');

    $booking = Booking::where('client_id', $this->client->id)->first();

    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Requested)
        ->and($booking->candidate_type)->toBe(EducationCandidate::class)
        ->and($booking->candidate_id)->toBe($this->candidate->id)
        ->and($booking->consultant_id)->toBe($this->consultant->id)
        ->and($booking->start_date->toDateString())->toBe('2026-09-01')
        ->and($booking->end_date->toDateString())->toBe('2026-09-02')
        ->and($booking->notes)->toBe('Needs someone for the whole week please.')
        ->and($booking->job_title_id)->toBeNull()
        ->and($booking->day_rate)->toBeNull()
        ->and($booking->day_charge_rate)->toBeNull();
});

test('the selected day-by-day sessions are saved as real booking day rows', function () {
    Livewire::test(MyCandidates::class)
        ->callTableAction('book', $this->candidate, data: [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'day_periods' => [
                ['date' => '2026-09-01', 'period' => 'am'],
                ['date' => '2026-09-02', 'period' => 'pm'],
                ['date' => '2026-09-03', 'period' => 'full_day'],
            ],
        ])
        ->assertHasNoTableActionErrors();

    $booking = Booking::where('client_id', $this->client->id)->first();
    $days = $booking->dayPeriods()->orderBy('date')->get();

    expect($days)->toHaveCount(3)
        ->and($days[0]->date->toDateString())->toBe('2026-09-01')
        ->and($days[0]->period)->toBe(BookingDayPeriod::Am)
        ->and($days[1]->period)->toBe(BookingDayPeriod::Pm)
        ->and($days[2]->period)->toBe(BookingDayPeriod::FullDay);
});

test('an end date before the start date is rejected', function () {
    Livewire::test(MyCandidates::class)
        ->callTableAction('book', $this->candidate, data: [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-05',
        ])
        ->assertHasTableActionErrors(['end_date']);

    expect(Booking::where('client_id', $this->client->id)->exists())->toBeFalse();
});

test('requesting a booking does not fire the booking confirmation pdf or emails', function () {
    Livewire::test(MyCandidates::class)
        ->callTableAction('book', $this->candidate, data: [
            'start_date' => '2026-09-01',
            'day_periods' => [
                ['date' => '2026-09-01', 'period' => 'full_day'],
            ],
        ]);

    Bus::assertNotDispatched(SendBookingConfirmationEmail::class);
    Bus::assertNotDispatched(SendClientBookingConfirmationEmail::class);
});

test('a request with no end date or notes is still created successfully as a single day', function () {
    Livewire::test(MyCandidates::class)
        ->callTableAction('book', $this->candidate, data: [
            'start_date' => '2026-09-01',
            'day_periods' => [
                ['date' => '2026-09-01', 'period' => 'full_day'],
            ],
        ])
        ->assertHasNoTableActionErrors();

    $booking = Booking::where('client_id', $this->client->id)->first();

    expect($booking->end_date)->toBeNull()
        ->and($booking->notes)->toBeNull()
        ->and($booking->dayPeriods()->count())->toBe(1);
});

test('a candidate not in the pool cannot be booked', function () {
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    Livewire::test(MyCandidates::class)
        ->assertCanNotSeeTableRecords([$otherCandidate]);
});
