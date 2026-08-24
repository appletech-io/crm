<?php

use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\Integration;
use App\Jobs\SendTimesheetToPayrollProvider;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\PaymentProvider;
use App\Models\ProviderError;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function makePayrollBooking(Company $company, array $bookingOverrides = [], array $candidateOverrides = []): Booking
{
    $client = Client::factory()->create(['company_id' => $company->id]);
    ClientContact::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'main_contact' => true,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);

    $candidate = EducationCandidate::factory()->create(array_merge([
        'company_id' => $company->id,
        'ni_number' => 'QQ123456C',
        'date_of_birth' => '1990-01-01',
        'gender' => 'female',
    ], $candidateOverrides));

    $booking = Booking::factory()->create(array_merge([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'status' => BookingStatus::AwaitingApproval,
        'day_rate' => 10000,
        'day_charge_rate' => 15000,
        // BookingFactory defaults this to an arbitrary fake date, which
        // would otherwise randomly interfere with submitTimesheet()'s
        // PeriodEndDate capping (an ended booking caps the cutoff at its
        // own end_date) — null (an open-ended booking) never caps it,
        // matching what every test not specifically about that behaviour
        // already assumes.
        'end_date' => null,
    ], $bookingOverrides));

    $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::FullDay->value,
        'payroll_confirmation_sent_at' => now(),
        'approved_at' => now(),
    ]);

    return $booking->fresh();
}

function fakeEvertimeCompany(): Company
{
    $company = Company::factory()->create([
        'payroll_provider' => Integration::Evertime->value,
    ]);

    $company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api-staging.evertime.co.uk');
    $company->setIntegrationSetting(Integration::Evertime, 'api_key', 'test-key');

    return $company;
}

test('approving a booking sends the client, candidate, placement and timesheet to the payroll provider', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $periodEndDate = TimesheetPeriod::current($company)['end']->toDateString();

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/clients')
        && $request['Name'] === $booking->client->name
        // Unlike Candidate/Company VatCode (short code), Client.Financials.VatCode
        // requires the full description string — Evertime rejects 'Standard'.
        && $request['Financials']['VatCode'] === 'Standard 20%');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/candidates')
        && $request['Forenames'] === $booking->candidate->first_name
        // Documented as optional, but Evertime rejects the request if this
        // key is absent entirely.
        && $request['SendEmailEnabled'] === false
        // Required for new candidates ("You must supply a StartDate for new
        // candidates.").
        && $request['StartDate'] === $booking->candidate->created_at->toDateString());
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/placements') && $request['PlacementId'] === "BOOKING-{$booking->id}");
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/timesheets')
        // Deliberately not sending TimesheetStatus at all — the live account
        // 500s when it's supplied explicitly alongside ApproverContactId
        // (any value, a confirmed Evertime-side bug), but it defaults to 5
        // (Approved) when omitted, which is what we always want anyway.
        && ! array_key_exists('TimesheetStatus', $request->data())
        && $request['ApproverContactId'] === "CONTACT-{$booking->client->mainContact->id}"
        && $request['ExternalTimesheetId'] === "BOOKING-{$booking->id}-{$periodEndDate}"
        && count($request['TimeEntries']) === 1
        && $request['TimeEntries'][0]['RateId'] === 'STD');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/placements')
        && $request['PlacementRates'][0]['RateId'] === 'STD'
        && $request['PlacementRates'][0]['RateType'] === 'TimesheetDays'
        && $request['PlacementRates'][0]['PayRate'] === $booking->day_rate
        && $request['PlacementRates'][0]['ChargeRate'] === $booking->day_charge_rate);

    expect($booking->dayPeriods()->first()->sent_to_provider_at)->not->toBeNull();
});

test('nothing is sent when the company has no payroll provider configured', function () {
    Http::fake();

    $company = Company::factory()->create(['payroll_provider' => null]);
    $booking = makePayrollBooking($company);

    $booking->update(['status' => BookingStatus::Approved]);

    // Client/Candidate creation may trigger unrelated geocoding HTTP calls
    // (see ClientObserver/EducationCandidateObserver), so this checks
    // specifically for no payroll-provider traffic rather than none at all.
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/clients')
        || str_ends_with($request->url(), '/candidates')
        || str_ends_with($request->url(), '/placements')
        || str_ends_with($request->url(), '/timesheets'));
});

test('a day already sent to the provider is not resent', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);
    $booking->update(['status' => BookingStatus::Approved]);

    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    // Re-triggering the same transition (e.g. another update touching status)
    // should not resend a day that's already marked sent_to_provider_at.
    $booking->update(['status' => BookingStatus::AwaitingApproval]);
    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertNothingSent();
});

test('a candidate with a payment provider sends an umbrella company payload keyed by its resolved external id', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $paymentProvider = PaymentProvider::factory()->create([
        'company_id' => $company->id,
        'name' => 'Orbital',
    ]);
    $paymentProvider->setProviderExternalId(Integration::Evertime, 'ORBITAL-EXISTING-1');

    $booking = makePayrollBooking($company, candidateOverrides: ['payment_provider_id' => $paymentProvider->id]);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/candidates')
        && $request['WorkerType'] === 'Umbrella Company'
        && $request['Company']['CompanyId'] === 'ORBITAL-EXISTING-1'
        && $request['Company']['Name'] === 'Orbital');
});

test('a client with a manually entered external id has it reused instead of a generated one', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);
    $booking->client->setProviderExternalId(Integration::Evertime, 'PRE-EXISTING-CLIENT-99');

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/clients') && $request['ClientId'] === 'PRE-EXISTING-CLIENT-99');
});

test('a booking with a manually entered placement id has it reused instead of a generated one', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);
    $booking->setProviderExternalId(Integration::Evertime, 'PRE-EXISTING-PLACEMENT-77');

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/placements') && $request['PlacementId'] === 'PRE-EXISTING-PLACEMENT-77');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/timesheets') && $request['PlacementId'] === 'PRE-EXISTING-PLACEMENT-77');
});

test('a generated placement id is persisted rather than reconstructed on each send', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $booking->update(['status' => BookingStatus::Approved]);

    expect($booking->providerExternalId(Integration::Evertime))->toBe("BOOKING-{$booking->id}")
        ->and($booking->providerExternalIds()->count())->toBe(1);
});

test('a 422 validation error from the provider is recorded and does not throw', function () {
    Http::fake(['*/clients' => Http::response([
        'HasErrors' => true,
        'Errors' => [['ErrorMessage' => "The supplied VatCode of 'Standard' is invalid."]],
    ], 422)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $booking->update(['status' => BookingStatus::Approved]);

    $providerError = ProviderError::where('booking_id', $booking->id)->first();

    expect($providerError)->not->toBeNull()
        ->and($providerError->provider)->toBe(Integration::Evertime->value)
        ->and($providerError->company_id)->toBe($company->id)
        ->and($providerError->errors)->toBe(["The supplied VatCode of 'Standard' is invalid."])
        ->and($booking->dayPeriods()->first()->sent_to_provider_at)->toBeNull();
});

test('a 200 response with HasErrors is recorded and does not throw', function () {
    Http::fake(['*' => Http::response([
        'HasErrors' => true,
        'Errors' => [['ErrorMessage' => 'Please supply a NINumber for candidate.']],
    ], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $booking->update(['status' => BookingStatus::Approved]);

    $providerError = ProviderError::where('booking_id', $booking->id)->first();

    expect($providerError)->not->toBeNull()
        ->and($providerError->errors)->toBe(['Please supply a NINumber for candidate.']);
});

test('a server error is rethrown for the queue to retry, but still recorded', function () {
    Http::fake(['*' => Http::response(['Message' => 'Internal error'], 500)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $job = new SendTimesheetToPayrollProvider($booking->fresh());

    expect(fn () => $job->handle())->toThrow(RequestException::class);

    $providerError = ProviderError::where('booking_id', $booking->id)->first();

    expect($providerError)->not->toBeNull();
});

test('a successful retry clears a previously recorded provider error', function () {
    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    ProviderError::create([
        'company_id' => $company->id,
        'booking_id' => $booking->id,
        'provider' => Integration::Evertime->value,
        'errors' => ['Some earlier failure'],
    ]);

    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $booking->update(['status' => BookingStatus::Approved]);

    expect(ProviderError::where('booking_id', $booking->id)->exists())->toBeFalse();
});

test('a booking with a consultant registers them and attributes the placement to them', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $consultant = User::factory()->create(['company_id' => $company->id, 'name' => 'Jamie Fox']);
    $booking = makePayrollBooking($company, ['consultant_id' => $consultant->id]);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/consultants')
        && $request['Consultants'][0]['ConsultantId'] === "CONSULTANT-{$consultant->id}"
        && $request['Consultants'][0]['Forenames'] === 'Jamie'
        && $request['Consultants'][0]['Surname'] === 'Fox'
        && $request['Consultants'][0]['EmailAddress'] === $consultant->email);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/placements')
        && $request['Consultants'][0]['ConsultantId'] === "CONSULTANT-{$consultant->id}"
        && $request['Consultants'][0]['Share'] === 100);
});

test('a booking spanning two payroll periods submits one timesheet per period with the correct cutoff', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $period1 = TimesheetPeriod::current($company);
    $period2 = TimesheetPeriod::next($company, $period1['start']);

    // Replace makePayrollBooking()'s single default day with two, one
    // falling in each of two consecutive weekly periods.
    $booking->dayPeriods()->delete();
    foreach ([$period1['end'], $period2['start']] as $date) {
        $booking->dayPeriods()->create([
            'company_id' => $company->id,
            'date' => $date->toDateString(),
            'period' => BookingDayPeriod::FullDay->value,
            'payroll_confirmation_sent_at' => now(),
            'approved_at' => now(),
        ]);
    }

    $booking->update(['status' => BookingStatus::Approved]);

    $timesheetRequests = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/timesheets'));

    expect($timesheetRequests)->toHaveCount(2);

    $periodEndDates = $timesheetRequests
        ->map(fn (array $pair) => $pair[0]['PeriodEndDate'])
        ->sort()
        ->values()
        ->all();

    expect($periodEndDates)->toBe([$period1['end']->toDateString(), $period2['end']->toDateString()])
        ->and($booking->dayPeriods()->whereNotNull('sent_to_provider_at')->count())->toBe(2);
});

test('a booking ending mid-period caps the timesheet cutoff at the booking end date, not the full period', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $period = TimesheetPeriod::current($company);

    // A booking that ends partway through the payroll period (e.g. a 3-day
    // booking inside a weekly period) registers that earlier date as the
    // placement's own EndDate in Evertime — the period's later calendar
    // boundary would be rejected as being after it.
    $booking = makePayrollBooking($company, [
        'start_date' => $period['start']->toDateString(),
        'end_date' => $period['start']->copy()->addDays(2)->toDateString(),
    ]);
    $booking->dayPeriods()->update(['date' => $period['start']->copy()->addDays(2)->toDateString()]);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/timesheets')
        && $request['PeriodEndDate'] === $period['start']->copy()->addDays(2)->toDateString());
});

test('a booking with no consultant omits Consultants from the placement payload', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/consultants'));
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/placements') && ! array_key_exists('Consultants', $request->data()));
});

test('the client contact who actually approved the days is registered and used as the timesheet approver', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $approverContact = ClientContact::factory()->create([
        'company_id' => $company->id,
        'client_id' => $booking->client_id,
        'first_name' => 'Priya',
        'last_name' => 'Shah',
    ]);
    $approverUser = User::factory()->create([
        'company_id' => $company->id,
        'client_contact_id' => $approverContact->id,
    ]);

    // makePayrollBooking() creates one already-approved day with no
    // approved_by_user_id — attribute it to this specific portal user
    // instead, as if they'd approved it via the client portal.
    $booking->dayPeriods()->update(['approved_by_user_id' => $approverUser->id]);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/clients/contacts')
        && $request['ClientId'] === "CLIENT-{$booking->client_id}"
        && $request['Contacts'][0]['ContactId'] === "CONTACT-{$approverContact->id}"
        && $request['Contacts'][0]['Forename'] === 'Priya'
        && $request['Contacts'][0]['Surname'] === 'Shah');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/timesheets')
        && $request['ApproverContactId'] === "CONTACT-{$approverContact->id}");
});

test('the most recent approver wins when different days in a period were approved by different contacts', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $earlierContact = ClientContact::factory()->create(['company_id' => $company->id, 'client_id' => $booking->client_id]);
    $earlierUser = User::factory()->create(['company_id' => $company->id, 'client_contact_id' => $earlierContact->id]);

    $laterContact = ClientContact::factory()->create(['company_id' => $company->id, 'client_id' => $booking->client_id]);
    $laterUser = User::factory()->create(['company_id' => $company->id, 'client_contact_id' => $laterContact->id]);

    // Anchor both days to the same weekly period explicitly, rather than
    // "today" and "tomorrow", so this doesn't flake if the test happens to
    // run on the last day of a period.
    $period = TimesheetPeriod::current($company);

    $existingDay = $booking->dayPeriods()->first();
    $existingDay->update([
        'date' => $period['start']->toDateString(),
        'approved_at' => now()->subHour(),
        'approved_by_user_id' => $earlierUser->id,
    ]);

    $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => $period['end']->toDateString(),
        'period' => BookingDayPeriod::FullDay->value,
        'payroll_confirmation_sent_at' => now(),
        'approved_at' => now(),
        'approved_by_user_id' => $laterUser->id,
    ]);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/timesheets')
        && $request['ApproverContactId'] === "CONTACT-{$laterContact->id}");
});

test('a half day time entry uses half a unit against the flat day rate, not a rate override', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    // Replace makePayrollBooking()'s single default (FullDay) day with an AM
    // half day, keeping the same date so it's still one period/one request.
    $booking->dayPeriods()->update(['period' => BookingDayPeriod::Am->value]);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/timesheets')
        && $request['TimeEntries'][0]['Units'] === 0.5
        && $request['TimeEntries'][0]['RateId'] === 'STD'
        && ! array_key_exists('PayRate', $request['TimeEntries'][0])
        && ! array_key_exists('ChargeRate', $request['TimeEntries'][0]));
});

test('an hourly time entry uses the STH rate code with start/end times, not STD', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company, [
        'day_rate' => null,
        'day_charge_rate' => null,
        'hourly_rate' => 1500,
        'hourly_charge_rate' => 2800,
    ]);
    $booking->dayPeriods()->update([
        'period' => BookingDayPeriod::Hours->value,
        'time_from' => '09:00:00',
        'time_to' => '17:00:00',
    ]);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/placements')
        && count($request['PlacementRates']) === 1
        && $request['PlacementRates'][0]['RateId'] === 'STH'
        && $request['PlacementRates'][0]['RateType'] === 'TimesheetHours'
        && $request['PlacementRates'][0]['PayRate'] === $booking->hourly_rate
        && $request['PlacementRates'][0]['ChargeRate'] === $booking->hourly_charge_rate);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/timesheets')
        && $request['TimeEntries'][0]['RateId'] === 'STH'
        && $request['TimeEntries'][0]['StartTime'] === '09:00:00'
        && $request['TimeEntries'][0]['EndTime'] === '17:00:00'
        && ! array_key_exists('Units', $request['TimeEntries'][0]));
});

test('a brand new client is created via a single clients POST with its contact and location', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/clients')
        && $request['Contacts'][0]['ContactId'] === "CONTACT-{$booking->client->mainContact->id}"
        && $request['Contacts'][0]['DefaultContact'] === true
        && $request['Locations'][0]['LocationId'] === "LOCATION-{$booking->client_id}");

    // submitTimesheet() separately registers whoever approved the days as
    // the timesheet approver (here, falling back to the client's default
    // contact) via the additive /clients/contacts endpoint — only the
    // /clients/locations sync from an already-known-client upsertClient()
    // is what shouldn't happen for a brand new client.
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/clients/locations'));
});

test('a client already known to the provider is updated via PUT, not the destructive clients POST', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company);
    $booking->client->setProviderExternalId(Integration::Evertime, 'PRE-EXISTING-CLIENT-99');

    $booking->update(['status' => BookingStatus::Approved]);

    // A client PUT can't touch Contacts/Locations at all, so there must be
    // no /clients POST — sending one here would risk deleting a contact (or
    // location) this app has no local record of, exactly as happened live
    // with a legacy contact still tied to an active placement.
    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/clients'));

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/clients')
        && $request['ClientId'] === 'PRE-EXISTING-CLIENT-99'
        && $request['MainContactId'] === "CONTACT-{$booking->client->mainContact->id}"
        && $request['DefaultLocationId'] === "LOCATION-{$booking->client_id}"
        && ! array_key_exists('Contacts', $request->data())
        && ! array_key_exists('Locations', $request->data()));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/clients/contacts')
        && $request['ClientId'] === 'PRE-EXISTING-CLIENT-99'
        && $request['Contacts'][0]['ContactId'] === "CONTACT-{$booking->client->mainContact->id}"
        && $request['Contacts'][0]['DefaultContact'] === true);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/clients/locations')
        && $request['ClientId'] === 'PRE-EXISTING-CLIENT-99'
        && $request['Locations'][0]['LocationId'] === "LOCATION-{$booking->client_id}");

    // The PUT references MainContactId/DefaultLocationId, so both must
    // already be registered before it runs — confirmed live via a 422
    // ("The supplied Main Contact does not exist for this Client") when the
    // PUT fired before the contact/location POSTs.
    $clientRequests = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/clients')
            || str_ends_with($pair[0]->url(), '/clients/contacts')
            || str_ends_with($pair[0]->url(), '/clients/locations'))
        ->map(fn (array $pair): string => $pair[0]->method())
        ->values();

    expect($clientRequests->search('PUT'))->toBeGreaterThan($clientRequests->search('POST'));

    // No specific portal approver was recorded on this booking's days, so
    // submitTimesheet()'s approver falls back to this same default contact.
    // It must not be re-registered with DefaultContact: false — confirmed
    // live via a 422 ("No default Client Contact supplied") on the
    // subsequent timesheet POST once that demoted the client's only default
    // contact.
    $contactRequests = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/clients/contacts'));

    expect($contactRequests)->toHaveCount(1);
    expect($contactRequests->first()[0]['Contacts'][0]['DefaultContact'])->toBeTrue();
});

test('a booking mixing day and hourly work sends both STD and STH placement rates', function () {
    Http::fake(['*' => Http::response(['HasErrors' => false, 'Errors' => []], 200)]);

    $company = fakeEvertimeCompany();
    $booking = makePayrollBooking($company, [
        'hourly_rate' => 1500,
        'hourly_charge_rate' => 2800,
    ]);

    $booking->update(['status' => BookingStatus::Approved]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/placements')
        && collect($request['PlacementRates'])->pluck('RateId')->sort()->values()->all() === ['STD', 'STH']);
});
