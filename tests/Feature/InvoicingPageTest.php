<?php

use App\Enums\BookingDayPeriod;
use App\Filament\Pages\Invoicing;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", 1);

    $this->company = $this->user->company;
    $this->periodStart = TimesheetPeriod::current($this->company)['start'];
});

function invoicingBooking(User $user, string $date, array $dayAttributes = [], array $bookingAttributes = []): Booking
{
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $user->company_id]);

    $booking = Booking::factory()->create(array_merge([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'day_rate' => 100,
        'day_charge_rate' => 150,
    ], $bookingAttributes));

    $booking->dayPeriods()->create(array_merge([
        'company_id' => $user->company_id,
        'date' => $date,
        'period' => BookingDayPeriod::FullDay,
    ], $dayAttributes));

    return $booking;
}

test('a non-admin cannot access the invoicing page', function () {
    $consultant = User::factory()->create(['company_id' => $this->user->company_id]);
    $consultant->assignRole('consultant');
    $this->actingAs($consultant);

    expect(Invoicing::canAccess())->toBeFalse();
});

test('an admin can access the invoicing page', function () {
    expect(Invoicing::canAccess())->toBeTrue();

    Livewire::test(Invoicing::class)->assertSuccessful();
});

test('the table only shows approved days, not pending or disputed ones', function () {
    $approved = invoicingBooking($this->user, $this->periodStart->toDateString(), ['approved_at' => now()]);
    $pending = invoicingBooking($this->user, $this->periodStart->toDateString());
    $disputed = invoicingBooking($this->user, $this->periodStart->toDateString(), ['disputed_at' => now(), 'dispute_reason' => 'Wrong hours']);

    Livewire::test(Invoicing::class)
        ->assertCanSeeTableRecords([$approved->dayPeriods()->first()])
        ->assertCanNotSeeTableRecords([$pending->dayPeriods()->first(), $disputed->dayPeriods()->first()]);
});

test('generating client invoices creates an invoice and downloads a zip', function () {
    invoicingBooking($this->user, $this->periodStart->toDateString(), ['approved_at' => now()]);

    Livewire::test(Invoicing::class)
        ->callTableAction('generateClientInvoices')
        ->assertNotified();

    expect(Invoice::count())->toBe(1);
});
