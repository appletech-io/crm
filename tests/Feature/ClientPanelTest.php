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
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->client = Client::factory()->create(['company_id' => $this->company->id]);

    $this->contact = ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
    ]);

    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'client_contact_id' => $this->contact->id,
    ]);
    $this->user->assignRole('client');
});

function sentDayForClientPanel(Booking $booking, string $date, array $attributes = [])
{
    return $booking->dayPeriods()->create(array_merge([
        'company_id' => $booking->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
        'payroll_confirmation_sent_at' => now(),
    ], $attributes));
}

test('a client role user can access the client panel', function () {
    $panel = Filament::getPanel('client');

    expect($this->user->canAccessPanel($panel))->toBeTrue();
});

test('a client role user cannot access the admin or candidate panels', function () {
    expect($this->user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($this->user->canAccessPanel(Filament::getPanel('candidate')))->toBeFalse();
});

test('an admin user cannot access the client panel', function () {
    $admin = User::factory()->create(['company_id' => $this->company->id]);
    $admin->assignRole('admin');

    expect($admin->canAccessPanel(Filament::getPanel('client')))->toBeFalse();
});

test('the my bookings page renders for a logged in client contact', function () {
    $this->actingAs($this->user);

    Livewire::test(MyBookings::class)->assertSuccessful();
});

test('it only shows bookings that have been sent for confirmation, scoped to this client', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    $sentDay = sentDayForClientPanel($booking, now()->toDateString());

    $booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->addDay()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $otherClient = Client::factory()->create(['company_id' => $this->company->id]);
    $otherBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $otherClient->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $otherClientDay = sentDayForClientPanel($otherBooking, now()->toDateString());

    $this->actingAs($this->user);

    Livewire::test(MyBookings::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$sentDay])
        ->assertCanNotSeeTableRecords([$otherClientDay]);
});

test('approving a day through the table row action marks it approved', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $day = sentDayForClientPanel($booking, now()->toDateString());

    $this->actingAs($this->user);

    Livewire::test(MyBookings::class)
        ->callTableAction('approveDay', $day);

    expect($day->fresh()->approved_at)->not->toBeNull();
});

test('disputing a day through the table row action requires a reason and stores it', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $day = sentDayForClientPanel($booking, now()->toDateString());

    $this->actingAs($this->user);

    Livewire::test(MyBookings::class)
        ->callTableAction('disputeDay', $day, data: ['reason' => ''])
        ->assertHasTableActionErrors(['reason']);

    Livewire::test(MyBookings::class)
        ->callTableAction('disputeDay', $day, data: ['reason' => 'Candidate did not attend'])
        ->assertHasNoTableActionErrors();

    $day->refresh();

    expect($day->disputed_at)->not->toBeNull()
        ->and($day->dispute_reason)->toBe('Candidate did not attend');
});

test('approving all days for a booking through the table row action marks the booking approved', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $dayOne = sentDayForClientPanel($booking, now()->toDateString());
    sentDayForClientPanel($booking, now()->addDay()->toDateString());

    $this->actingAs($this->user);

    Livewire::test(MyBookings::class)
        ->callTableAction('approveBooking', $dayOne);

    expect($booking->fresh()->status)->toBe(BookingStatus::Approved);
});

test('a client contact cannot act on another clients booking day through a tampered action call', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $otherClient = Client::factory()->create(['company_id' => $this->company->id]);
    $otherBooking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $otherClient->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $otherDay = sentDayForClientPanel($otherBooking, now()->toDateString());

    $this->actingAs($this->user);

    $component = Livewire::test(MyBookings::class);

    expect(fn () => $component->callTableAction('approveDay', $otherDay))
        ->toThrow(ActionNotResolvableException::class);

    expect($otherDay->fresh()->approved_at)->toBeNull();
});

test('days are grouped by candidate', function () {
    $candidateA = EducationCandidate::factory()->create(['company_id' => $this->company->id]);
    $candidateB = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    $bookingA = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidateA->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);
    $bookingB = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $candidateB->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
    ]);

    sentDayForClientPanel($bookingA, now()->toDateString());
    sentDayForClientPanel($bookingB, now()->toDateString());

    $this->actingAs($this->user);

    $component = Livewire::test(MyBookings::class)->assertSuccessful();

    expect($component->instance()->getTableRecords()->pluck('booking.candidate_id')->unique())
        ->toHaveCount(2);
});
