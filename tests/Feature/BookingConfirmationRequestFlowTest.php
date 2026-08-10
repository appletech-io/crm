<?php

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Jobs\GenerateBookingConfirmationPdf;
use App\Jobs\SendBookingConfirmationEmail;
use App\Jobs\SendClientBookingConfirmationEmail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    // Faked before any factory runs below: Client::factory() triggers
    // ClientObserver's real (synchronous, QUEUE_CONNECTION=sync in tests)
    // GeocodeClient dispatch, which makes a live HTTP call — faking Queue
    // late (inside each test) let that call fire during setup instead.
    Queue::fake();

    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->client = Client::factory()->create(['company_id' => $this->user->company_id]);
    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
});

test('confirming a request with no job title set fails validation and leaves the status unchanged', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => null,
        'status' => BookingStatus::Requested,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('confirmBooking')
        ->assertNotified('Set a job title and pay/charge rates before confirming.');

    Queue::assertNotPushed(GenerateBookingConfirmationPdf::class);
    expect($booking->fresh()->status)->toBe(BookingStatus::Requested);
});

test('confirming a request with a job title and rates set flips the status and fires the confirmation flow', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'day_rate' => 100,
        'day_charge_rate' => 150,
        'status' => BookingStatus::Requested,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('confirmBooking')
        ->assertNotified('Booking confirmed — confirmation emails queued.');

    expect($booking->fresh()->status)->toBe(BookingStatus::Upcoming);

    Queue::assertPushed(GenerateBookingConfirmationPdf::class, fn ($job) => $job->booking->is($booking));
    Queue::assertPushed(SendBookingConfirmationEmail::class, fn ($job) => $job->booking->is($booking));
    Queue::assertPushed(SendClientBookingConfirmationEmail::class, fn ($job) => $job->booking->is($booking));
});

test('the confirm and reject actions are only visible while the booking is requested', function () {
    $requested = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'status' => BookingStatus::Requested,
    ]);
    $upcoming = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'status' => BookingStatus::Upcoming,
    ]);

    Livewire::test(EditBooking::class, ['record' => $requested->getRouteKey()])
        ->assertActionVisible('confirmBooking')
        ->assertActionVisible('rejectBooking');

    Livewire::test(EditBooking::class, ['record' => $upcoming->getRouteKey()])
        ->assertActionHidden('confirmBooking')
        ->assertActionHidden('rejectBooking');
});

test('resend confirmation emails is only visible while the booking is upcoming', function () {
    $statusesWhereHidden = [
        BookingStatus::Requested,
        BookingStatus::AwaitingApproval,
        BookingStatus::Approved,
        BookingStatus::Completed,
    ];

    foreach ($statusesWhereHidden as $status) {
        $booking = Booking::factory()->create([
            'company_id' => $this->user->company_id,
            'client_id' => $this->client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'status' => $status,
        ]);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('resendConfirmationEmails');
    }

    $upcoming = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'status' => BookingStatus::Upcoming,
    ]);

    Livewire::test(EditBooking::class, ['record' => $upcoming->getRouteKey()])
        ->assertActionVisible('resendConfirmationEmails');
});

test('rejecting a request soft-deletes the booking and the client is not notified', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'status' => BookingStatus::Requested,
    ]);

    Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('rejectBooking')
        ->assertNotified('Booking request rejected');

    expect(Booking::find($booking->id))->toBeNull()
        ->and(Booking::withTrashed()->find($booking->id)?->trashed())->toBeTrue();

    Queue::assertNotPushed(GenerateBookingConfirmationPdf::class);
});

test('opening a fresh request with no day periods yet auto-fills the schedule from the requested date range', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'status' => BookingStatus::Requested,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
    ]);

    expect($booking->dayPeriods()->count())->toBe(0);

    $dayPeriods = Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->instance()
        ->form
        ->getRawState()['day_periods'];

    expect(array_values($dayPeriods))->toBe([
        ['date' => '2026-09-01', 'period' => 'full_day', 'time_from' => null, 'time_to' => null, 'cancelled' => false, 'dispute_reason' => null],
        ['date' => '2026-09-02', 'period' => 'full_day', 'time_from' => null, 'time_to' => null, 'cancelled' => false, 'dispute_reason' => null],
        ['date' => '2026-09-03', 'period' => 'full_day', 'time_from' => null, 'time_to' => null, 'cancelled' => false, 'dispute_reason' => null],
    ]);
});

test('a booking that already has day periods is not overwritten by the fallback fill', function () {
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'status' => BookingStatus::Requested,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-09-01',
        'period' => 'am',
    ]);

    $dayPeriods = Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
        ->instance()
        ->form
        ->getRawState()['day_periods'];

    expect(array_values($dayPeriods))->toBe([
        ['date' => '2026-09-01', 'period' => 'am', 'time_from' => null, 'time_to' => null, 'cancelled' => false, 'disputed' => false, 'dispute_reason' => null],
    ]);
});
