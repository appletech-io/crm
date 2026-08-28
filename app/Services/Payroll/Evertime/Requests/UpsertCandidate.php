<?php

namespace App\Services\Payroll\Evertime\Requests;

use App\Models\PaymentProvider;
use App\Services\Payroll\Evertime\EvertimeClient;
use Illuminate\Database\Eloquent\Model;

/** POST /candidates */
class UpsertCandidate
{
    public function __construct(private readonly EvertimeClient $client) {}

    /** @param  Model  $candidate  EducationCandidate|HealthcareCandidate */
    public function handle(Model $candidate, string $candidateId, ?PaymentProvider $paymentProvider, ?string $paymentProviderId): void
    {
        $payload = [
            // Documented as optional, but Evertime rejects the request
            // ("Must contain a SendEmailEnabled field.") if the key is
            // absent entirely. False because candidates only interact with
            // this app, not Evertime's own login/approval flow.
            'SendEmailEnabled' => false,
            'CandidateId' => $candidateId,
            'NINumber' => $candidate->ni_number,
            'NICategory' => 'A',
            // Required for new candidates ("You must supply a StartDate for
            // new candidates."). Only re-validated on updates when
            // CandidateStatus isn't "Open", so this app's own record of when
            // the candidate joined is a safe, stable value to keep resending.
            'StartDate' => $candidate->created_at->toDateString(),
            'Forenames' => $candidate->first_name,
            'Surname' => $candidate->last_name,
            'Address' => [
                'AddressLine1' => $candidate->address,
                // Mandatory per Evertime, but this app only stores a single
                // address line for candidates — city is the closest
                // second-line equivalent we have.
                'AddressLine2' => $candidate->city ?: $candidate->address,
                'City' => $candidate->city,
                'County' => $candidate->county,
                'PostCode' => $candidate->postcode,
            ],
            'DateOfBirth' => $candidate->date_of_birth?->toDateString(),
            'Gender' => $this->genderFor($candidate),
            'Email' => $candidate->email,
            'WorkerType' => 'Paye',
        ];

        if (! $paymentProvider) {
            // SortCode/AccountNumber are documented as "Only used if PAYE" —
            // an umbrella/Ltd candidate is paid via their Company's own bank
            // details instead, not the candidate's personal account.
            $payload['AccountNumber'] = $candidate->bank_account_number;
            $payload['SortCode'] = $candidate->bank_sort_code;
        }

        if ($paymentProvider) {
            $payload['WorkerType'] = 'Umbrella Company';
            $payload['Company'] = [
                'CompanyId' => $paymentProviderId,
                'Type' => 'Umbrella',
                // Mandatory — "Must contain a forename and surname". Falls
                // back to the company name for a payment provider that
                // predates these contact fields, rather than sending a
                // blank string Evertime would reject outright.
                'MainContact' => trim("{$paymentProvider->contact_first_name} {$paymentProvider->contact_last_name}") ?: $paymentProvider->name,
                'MainContactPhoneNumber' => $paymentProvider->contact_phone,
                'CommunicationMethod' => 'Email',
                'VatCode' => 'Standard',
                'Name' => $paymentProvider->name,
                // Required by Evertime for a new en-GB company — a genuinely
                // new umbrella company left without one may be rejected, in
                // which case it needs creating in Evertime's own UI first
                // and its ID pasted in here instead.
                'CompanyRegNumber' => $paymentProvider->company_reg_number,
                'VatRegNumber' => $paymentProvider->vat_reg_number,
                'Utr' => $paymentProvider->utr,
                'AccountName' => $paymentProvider->bank_account_name,
                'AccountNumber' => $paymentProvider->bank_account_number,
                'SortCode' => $paymentProvider->bank_sort_code,
                'BankName' => $paymentProvider->bank_name,
                'Email' => $paymentProvider->email,
                'PhoneNumber' => $paymentProvider->phone,
                'DefaultCurrency' => 'GBP',
                'Address' => [
                    'AddressLine1' => $paymentProvider->address_1,
                    'AddressLine2' => $paymentProvider->address_2,
                    'County' => $paymentProvider->county,
                    'PostCode' => $paymentProvider->postcode,
                ],
            ];
        }

        $this->client->post('/candidates', $payload);
    }

    private function genderFor(Model $candidate): string
    {
        // Evertime's Gender field is mandatory and only documented as
        // Male/Female — this app's gender options are broader, so anything
        // that isn't a direct match falls back to Male rather than leaving
        // the (mandatory) field blank. Worth revisiting with Evertime
        // support if that's not acceptable.
        return match ($candidate->gender) {
            'female' => 'Female',
            default => 'Male',
        };
    }
}
