<?php

use App\Actions\Bookings\BookingCreated;
use App\Enums\ActivityType;
use App\Enums\BookingDayPeriod;
use App\Enums\DocumentType;
use App\Enums\EmailTemplateType;
use App\Jobs\GenerateBookingConfirmationPdf;
use App\Jobs\SendBookingConfirmationEmail;
use App\Jobs\SendClientBookingConfirmationEmail;
use App\Models\Booking;
use App\Models\CandidateActivity;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Booking\BookingDayPeriods;
use App\Services\Education\BookingConfirmationLink;
use App\Services\Education\BookingConfirmationPdfService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

beforeEach(function () {
    Storage::fake('local');

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['name' => 'Education', 'slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Planning Teacher',
    ]);

    $this->client = Client::factory()->create(['company_id' => $this->company->id]);

    $this->candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Mr',
        'first_name' => 'Stephen',
        'last_name' => 'Platts',
        'email' => 'stephen@example.com',
        'date_of_birth' => '1966-03-12',
        'ni_number' => 'NH741912A',
        'dbs_certificate_number' => '001912886570',
    ]);

    $this->booking = Booking::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $this->jobTitle->id,
        'day_rate' => 200,
    ]);
});

test('the service generates a pdf and stores it under the candidate bookings folder', function () {
    $path = app(BookingConfirmationPdfService::class)->generate($this->booking);

    expect($path)->toContain("/education/{$this->candidate->id}/bookings/")
        ->and($path)->toEndWith("booking-{$this->booking->id}-confirmation.pdf");

    Storage::disk('local')->assertExists($path);

    $absolute = Storage::disk('local')->path($path);
    expect(substr(file_get_contents($absolute), 0, 4))->toBe('%PDF');
});

test('the pdf merges in the dbs front, back, and safeguarding certificate when present', function () {
    $this->candidate->documents()->create([
        'document_type' => DocumentType::DbsFront,
        'path' => 'placeholder/dbs-front.png',
    ]);
    $this->candidate->documents()->create([
        'document_type' => DocumentType::DbsBack,
        'path' => 'placeholder/dbs-back.png',
    ]);

    Storage::disk('local')->put('placeholder/dbs-front.png', file_get_contents(base_path('public/images/applebough.png')));
    Storage::disk('local')->put('placeholder/dbs-back.png', file_get_contents(base_path('public/images/applebough.png')));

    $path = app(BookingConfirmationPdfService::class)->generate($this->booking);

    $absolute = Storage::disk('local')->path($path);
    $pdf = new Fpdi;
    $pageCount = $pdf->setSourceFile($absolute);

    expect($pageCount)->toBe(3);
});

test('the pdf has just the summary page when no dbs or safeguarding documents exist', function () {
    $path = app(BookingConfirmationPdfService::class)->generate($this->booking);

    $absolute = Storage::disk('local')->path($path);
    $pdf = new Fpdi;
    $pageCount = $pdf->setSourceFile($absolute);

    expect($pageCount)->toBe(1);
});

test('the candidate photo is embedded on the summary page rather than appended as its own page', function () {
    $this->candidate->documents()->create([
        'document_type' => DocumentType::Photo,
        'path' => 'placeholder/photo.png',
    ]);

    Storage::disk('local')->put('placeholder/photo.png', file_get_contents(base_path('public/images/applebough.png')));

    $path = app(BookingConfirmationPdfService::class)->generate($this->booking);

    $absolute = Storage::disk('local')->path($path);
    $pdf = new Fpdi;
    $pageCount = $pdf->setSourceFile($absolute);

    expect($pageCount)->toBe(1);
});

test('the booking confirmation view renders the candidate photo when present and omits it when absent', function () {
    $viewData = [
        'booking' => $this->booking,
        'candidate' => $this->candidate,
        'checks' => collect(),
        'bookingDates' => collect(),
    ];

    $withoutPhoto = view('pdfs.booking-confirmation', [...$viewData, 'photoDataUri' => null])->render();
    expect($withoutPhoto)->not->toContain('class="photo"');

    $withPhoto = view('pdfs.booking-confirmation', [...$viewData, 'photoDataUri' => 'data:image/png;base64,Zm9v'])->render();
    expect($withPhoto)->toContain('class="photo"')
        ->and($withPhoto)->toContain('src="data:image/png;base64,Zm9v"');
});

test('the pdf includes a booking dates table with the charge rate and, for hours-based days, a start-to-end time', function () {
    $this->booking->update(['day_charge_rate' => 270, 'hourly_charge_rate' => 25]);

    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => '2026-06-29',
        'period' => BookingDayPeriod::FullDay,
        'time_from' => '08:30:00',
    ]);

    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => '2026-06-30',
        'period' => BookingDayPeriod::Hours,
        'time_from' => '09:00:00',
        'time_to' => '17:00:00',
    ]);

    $rows = BookingDayPeriods::rows($this->booking->fresh(), 'charge');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['start'])->toBe('08:30')
        ->and($rows[0]['rate'])->toBe(270.0)
        ->and($rows[0]['hours'])->toBeNull()
        ->and($rows[1]['start'])->toBe('09:00 - 17:00')
        ->and($rows[1]['rate'])->toBe(25.0)
        ->and($rows[1]['hours'])->toBe(8.0);

    $html = view('pdfs.booking-confirmation', [
        'booking' => $this->booking,
        'candidate' => $this->candidate,
        'checks' => collect(),
        'bookingDates' => $rows,
        'photoDataUri' => null,
    ])->render();

    expect($html)->toContain('Booking Date(s)')
        ->and($html)->toContain('29/06/2026')
        ->and($html)->toContain('£270.00')
        ->and($html)->toContain('09:00 - 17:00')
        ->and($html)->toContain('£25.00');
});

test('a cancelled day shows as cancelled with no rate in the breakdown and pdf', function () {
    $this->booking->update(['day_charge_rate' => 270]);

    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => '2026-06-29',
        'period' => BookingDayPeriod::FullDay,
        'cancelled_at' => now(),
    ]);

    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => '2026-06-30',
        'period' => BookingDayPeriod::FullDay,
    ]);

    $rows = BookingDayPeriods::rows($this->booking->fresh(), 'charge');

    expect($rows[0]['cancelled'])->toBeTrue()
        ->and($rows[0]['rate'])->toBeNull()
        ->and($rows[1]['cancelled'])->toBeFalse()
        ->and($rows[1]['rate'])->toBe(270.0);

    $html = view('pdfs.booking-confirmation', [
        'booking' => $this->booking,
        'candidate' => $this->candidate,
        'checks' => collect(),
        'bookingDates' => $rows,
        'photoDataUri' => null,
    ])->render();

    expect($html)->toContain('Cancelled')
        ->and($html)->toContain('£270.00');
});

test('the pdf view never renders a pay rates section, only the booking dates charge rate table', function () {
    $html = view('pdfs.booking-confirmation', [
        'booking' => $this->booking,
        'candidate' => $this->candidate,
        'checks' => collect(),
        'bookingDates' => collect(),
        'photoDataUri' => null,
    ])->render();

    expect($html)->not->toContain('Pay Rates')
        ->and($html)->not->toContain('Day Rate')
        ->and($html)->not->toContain('Half Day Rate')
        ->and($html)->not->toContain('Hourly Rate');
});

test('BookingCreated dispatches the pdf generation job and both confirmation email jobs', function () {
    Queue::fake();

    BookingCreated::run($this->booking);

    Queue::assertPushed(GenerateBookingConfirmationPdf::class, fn (GenerateBookingConfirmationPdf $job) => $job->booking->is($this->booking));
    Queue::assertPushed(SendBookingConfirmationEmail::class, fn (SendBookingConfirmationEmail $job) => $job->booking->is($this->booking));
    Queue::assertPushed(SendClientBookingConfirmationEmail::class, fn (SendClientBookingConfirmationEmail $job) => $job->booking->is($this->booking));
});

test('the generation job stores the pdf path on the booking', function () {
    (new GenerateBookingConfirmationPdf($this->booking))->handle(app(BookingConfirmationPdfService::class));

    expect($this->booking->fresh()->confirmation_pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($this->booking->fresh()->confirmation_pdf_path);
});

test('the email job does nothing when no booking confirmation email template exists', function () {
    Http::fake();

    $this->booking->update(['confirmation_pdf_path' => 'somewhere/booking.pdf']);
    Storage::disk('local')->put('somewhere/booking.pdf', '%PDF-1.7 test');

    (new SendBookingConfirmationEmail($this->booking))->handle();

    Http::assertNothingSent();
    expect(CandidateActivity::count())->toBe(0);
});

test('the email job includes the booking breakdown and a crypt-secured pdf link, and logs an activity, when a template exists', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    $this->client->update(['name' => 'Ashlawn School', 'phone' => '01788 532823']);

    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => '2026-06-29',
        'period' => BookingDayPeriod::FullDay,
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Candidate Booking Confirmation',
        'type' => EmailTemplateType::CandidateBookingConfirmation,
        'subject' => 'Booking confirmed for {firstname}',
        'body' => 'Dear {candidate_name}, your booking ({booking_ref}) as {job_title} for {client_name} is confirmed. {day_breakdown} View the PDF: {application_pdf_link}',
    ]);

    $this->booking->update(['confirmation_pdf_path' => 'somewhere/booking.pdf']);
    Storage::disk('local')->put('somewhere/booking.pdf', '%PDF-1.7 test');

    (new SendBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'graph.microsoft.com')) {
            return false;
        }

        $body = $request['message']['body']['content'];

        return $request['message']['subject'] === 'Booking confirmed for Stephen'
            && str_contains($body, 'Dear Mr. Stephen Platts')
            && str_contains($body, "({$this->booking->id})")
            && str_contains($body, 'Ashlawn School')
            && str_contains($body, 'Booking Date')
            && str_contains($body, '29/06/2026')
            && str_contains($body, 'Full Day')
            && str_contains($body, '£200.00')
            && str_contains($body, route('booking-confirmation.show'))
            && empty($request['message']['attachments'] ?? []);
    });

    expect(CandidateActivity::where('type', ActivityType::Email)->count())->toBe(1);
});

test('the email job sends from the candidate consultant when one is assigned', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
    $this->candidate->update(['consultant_id' => $consultant->id]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Candidate Booking Confirmation',
        'type' => EmailTemplateType::CandidateBookingConfirmation,
        'subject' => 'Booking confirmed',
        'body' => 'Confirmed.',
    ]);

    (new SendBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/consultant@example.com/sendMail'));
});

test('the email job falls back to the company sender email when the candidate has no consultant', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Candidate Booking Confirmation',
        'type' => EmailTemplateType::CandidateBookingConfirmation,
        'subject' => 'Booking confirmed',
        'body' => 'Confirmed.',
    ]);

    (new SendBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/sender@example.com/sendMail'));
});

test('the candidate breakdown shows the start-to-end time for hours-based days and pulls the phone number from the company', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
        'phone' => '0121 827 4646',
    ]);

    $this->booking->update(['hourly_rate' => 25]);

    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => '2026-06-29',
        'period' => BookingDayPeriod::Hours,
        'time_from' => '09:00:00',
        'time_to' => '12:30:00',
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Candidate Booking Confirmation',
        'type' => EmailTemplateType::CandidateBookingConfirmation,
        'subject' => 'Booking confirmed',
        'body' => 'Call us on {company_phone}. {day_breakdown}',
    ]);

    (new SendBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com')
        && str_contains($request['message']['body']['content'], '0121 827 4646')
        && str_contains($request['message']['body']['content'], '09:00 - 12:30')
        && str_contains($request['message']['body']['content'], '£25.00'));
});

test('the email job still sends and includes a working pdf link even before the pdf has been generated', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Candidate Booking Confirmation',
        'type' => EmailTemplateType::CandidateBookingConfirmation,
        'subject' => 'Booking confirmed for {firstname}',
        'body' => 'View the PDF: {application_pdf_link}',
    ]);

    expect($this->booking->confirmation_pdf_path)->toBeNull();

    (new SendBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com')
        && str_contains($request['message']['body']['content'], route('booking-confirmation.show')));

    expect(CandidateActivity::where('type', ActivityType::Email)->count())->toBe(1);
});

test('the crypt-secured link resolves to the stored pdf and rejects a tampered token', function () {
    $this->booking->update(['confirmation_pdf_path' => 'somewhere/booking.pdf']);
    Storage::disk('local')->put('somewhere/booking.pdf', '%PDF-1.7 test');

    $url = BookingConfirmationLink::url($this->booking);

    $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->get(route('booking-confirmation.show', ['crypt' => 'not-a-real-token']))->assertNotFound();
});

test('the client email job sends to the booking contact with the charge rate breakdown and logs a client activity', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    $this->client->update(['name' => 'Ashlawn School']);

    $this->client->contacts()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Clare',
        'last_name' => 'Webster',
        'title' => 'Mrs',
        'email' => 'main-contact@example.com',
        'main_contact' => true,
    ]);

    $bookingContact = $this->client->contacts()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Booking',
        'last_name' => 'Contact',
        'email' => 'booking-contact@example.com',
        'booking_contact' => true,
    ]);

    $this->booking->update(['day_charge_rate' => 225]);

    $this->booking->dayPeriods()->create([
        'company_id' => $this->company->id,
        'date' => '2026-06-29',
        'period' => BookingDayPeriod::FullDay,
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Client Booking Confirmation',
        'type' => EmailTemplateType::ClientBookingConfirmation,
        'subject' => 'Booking confirmed for {client_name}',
        'body' => 'Dear {client_contact_name}, {candidate_name} is booked as {job_title} ({booking_ref}). {day_breakdown} View: {application_pdf_link}',
    ]);

    $this->booking->update(['confirmation_pdf_path' => 'somewhere/booking.pdf']);
    Storage::disk('local')->put('somewhere/booking.pdf', '%PDF-1.7 test');

    (new SendClientBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(function ($request) use ($bookingContact) {
        if (! str_contains($request->url(), 'graph.microsoft.com')) {
            return false;
        }

        $body = $request['message']['body']['content'];

        return $request['message']['subject'] === 'Booking confirmed for Ashlawn School'
            && $request['message']['toRecipients'][0]['emailAddress']['address'] === $bookingContact->email
            && str_contains($body, 'Dear Booking Contact')
            && str_contains($body, 'Stephen Platts')
            && str_contains($body, 'Charge Rate')
            && str_contains($body, '29/06/2026')
            && str_contains($body, '£225.00')
            && str_contains($body, '<a href="'.route('booking-confirmation.show'))
            && str_contains($body, '>Booking Confirmation</a>');
    });

    expect(ClientActivity::where('type', ActivityType::Email)->count())->toBe(1);
});

test('the client email job sends from the client consultant when one is assigned', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    $consultant = User::factory()->create(['company_id' => $this->company->id, 'email' => 'consultant@example.com']);
    $this->client->update(['consultant_id' => $consultant->id]);

    $this->client->contacts()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Main',
        'last_name' => 'Contact',
        'email' => 'main-contact@example.com',
        'main_contact' => true,
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Client Booking Confirmation',
        'type' => EmailTemplateType::ClientBookingConfirmation,
        'subject' => 'Booking confirmed',
        'body' => 'Confirmed.',
    ]);

    (new SendClientBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/consultant@example.com/sendMail'));
});

test('the client email job falls back to the company sender email when the client has no consultant', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    $this->client->contacts()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Main',
        'last_name' => 'Contact',
        'email' => 'main-contact@example.com',
        'main_contact' => true,
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Client Booking Confirmation',
        'type' => EmailTemplateType::ClientBookingConfirmation,
        'subject' => 'Booking confirmed',
        'body' => 'Confirmed.',
    ]);

    (new SendClientBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/sender@example.com/sendMail'));
});

test('the client email job still sends and includes a working pdf link even before the pdf has been generated', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    $this->client->contacts()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Clare',
        'last_name' => 'Webster',
        'email' => 'main-contact@example.com',
        'main_contact' => true,
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Client Booking Confirmation',
        'type' => EmailTemplateType::ClientBookingConfirmation,
        'subject' => 'Booking confirmed',
        'body' => 'View: {application_pdf_link}',
    ]);

    expect($this->booking->confirmation_pdf_path)->toBeNull();

    (new SendClientBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com')
        && str_contains($request['message']['body']['content'], '<a href="'.route('booking-confirmation.show'))
        && str_contains($request['message']['body']['content'], '>Booking Confirmation</a>'));

    expect(ClientActivity::where('type', ActivityType::Email)->count())->toBe(1);
});

test('the client email job falls back to the main contact when there is no booking contact', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $this->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);

    $mainContact = $this->client->contacts()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Clare',
        'last_name' => 'Webster',
        'email' => 'main-contact@example.com',
        'main_contact' => true,
    ]);

    EmailTemplate::create([
        'company_id' => $this->company->id,
        'industry_id' => 1,
        'name' => 'Client Booking Confirmation',
        'type' => EmailTemplateType::ClientBookingConfirmation,
        'subject' => 'Booking confirmed',
        'body' => 'Body',
    ]);

    $this->booking->update(['confirmation_pdf_path' => 'somewhere/booking.pdf']);
    Storage::disk('local')->put('somewhere/booking.pdf', '%PDF-1.7 test');

    (new SendClientBookingConfirmationEmail($this->booking))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com')
        && $request['message']['toRecipients'][0]['emailAddress']['address'] === $mainContact->email
    );
});

test('the client email job does nothing when the client has no contact with an email', function () {
    Http::fake();

    $this->booking->update(['confirmation_pdf_path' => 'somewhere/booking.pdf']);
    Storage::disk('local')->put('somewhere/booking.pdf', '%PDF-1.7 test');

    (new SendClientBookingConfirmationEmail($this->booking))->handle();

    Http::assertNothingSent();
    expect(ClientActivity::count())->toBe(0);
});
