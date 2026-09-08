<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Jobs\GenerateBookingConfirmationPdf;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Education\BookingConfirmationPdfService;
use App\Services\Healthcare\BookingConfirmationChecks;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['name' => 'Healthcare', 'slug' => 'healthcare']);
    $this->company->industries()->attach($this->industry);

    $this->admin = User::factory()->create(['company_id' => $this->company->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
    Cache::put("user.{$this->admin->id}.active_industry", 'healthcare');
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);

    $this->candidate = HealthcareCandidate::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Anna',
        'last_name' => 'Okafor',
        'date_of_birth' => '1988-04-02',
        'ni_number' => 'NH741912A',
        'dbs_certificate_number' => '001912886570',
        'professional_registration_body' => 'NMC',
        'professional_registration_number' => '99A1234B',
    ]);

    $this->client = Client::factory()->create(['company_id' => $this->company->id]);
    $this->jobTitle = JobTitle::factory()->create(['company_id' => $this->company->id]);

    $this->booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => HealthcareCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'consultant_id' => $this->admin->id,
        'status' => BookingStatus::Upcoming,
    ]);

    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => now()->addWeek()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);
});

test('a healthcare booking can generate its confirmation PDF', function () {
    $path = app(BookingConfirmationPdfService::class)->generate($this->booking);

    expect($path)->toContain("booking-{$this->booking->id}-confirmation.pdf")
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

test('the healthcare vetting checks cover registration rather than education-only rows', function () {
    $labels = collect(BookingConfirmationChecks::for($this->candidate))->pluck('label');

    expect($labels)->toContain('Registration Body', 'Registration No', 'DBS No', 'Date of Birth')
        ->and($labels)->not->toContain('TRN', 'Safeguarding Training', "Benedict's Law Training");

    $values = collect(BookingConfirmationChecks::for($this->candidate))->pluck('value', 'label');

    expect($values['Registration Body'])->toBe('NMC')
        ->and($values['Registration No'])->toBe('99A1234B')
        ->and($values['Date of Birth'])->toBe('2nd Apr 1988');
});

test('the generate button is offered when no PDF exists, and dispatches the job', function () {
    Queue::fake();

    Livewire::test(EditBooking::class, ['record' => $this->booking->getRouteKey()])
        ->assertActionVisible('generateConfirmationPdf')
        ->assertActionHidden('viewConfirmationPdf')
        ->callAction('generateConfirmationPdf');

    Queue::assertPushed(GenerateBookingConfirmationPdf::class);
});

test('the view button replaces the generate button once the PDF is on disk', function () {
    $path = app(BookingConfirmationPdfService::class)->generate($this->booking);
    $this->booking->update(['confirmation_pdf_path' => $path]);

    Livewire::test(EditBooking::class, ['record' => $this->booking->getRouteKey()])
        ->assertActionVisible('viewConfirmationPdf')
        ->assertActionHidden('generateConfirmationPdf');
});

test('a path pointing at a missing file falls back to the generate button', function () {
    $this->booking->update(['confirmation_pdf_path' => 'candidates/gone/bookings/booking-x-confirmation.pdf']);

    Livewire::test(EditBooking::class, ['record' => $this->booking->getRouteKey()])
        ->assertActionHidden('viewConfirmationPdf')
        ->assertActionVisible('generateConfirmationPdf');
});
