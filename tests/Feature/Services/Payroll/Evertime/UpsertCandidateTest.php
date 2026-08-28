<?php

use App\Enums\Integration;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\PaymentProvider;
use App\Services\Payroll\Evertime\EvertimeClient;
use App\Services\Payroll\Evertime\Requests\UpsertCandidate;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->company->setIntegrationSetting(Integration::Evertime, 'api_url', 'https://api.evertime.test');
    $this->company->setIntegrationSetting(Integration::Evertime, 'api_key', 'test-key');

    Http::fake(['*' => Http::response(['HasErrors' => false])]);
});

test('the Company payload sends the payment provider\'s real contact and financial details', function () {
    $paymentProvider = PaymentProvider::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Orbital Umbrella Ltd',
        'address_1' => '1 Orbital Way',
        'address_2' => 'Suite 2',
        'county' => 'Greater London',
        'postcode' => 'EC1A 1AA',
        'contact_first_name' => 'Priya',
        'contact_last_name' => 'Nair',
        'contact_phone' => '01206555444',
        'email' => 'accounts@orbital.test',
        'phone' => '01206555000',
        'company_reg_number' => 'AB123456',
        'vat_reg_number' => 'GB123456789',
        'utr' => '1234567890',
        'bank_name' => 'Orbital Bank',
        'bank_account_name' => 'Orbital Umbrella Ltd',
        'bank_account_number' => '12345678',
        'bank_sort_code' => '123456',
    ]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    (new UpsertCandidate(new EvertimeClient($this->company)))
        ->handle($candidate, 'CANDIDATE-1', $paymentProvider, 'COMPANY-1');

    Http::assertSent(function ($request) {
        $company = $request->data()['Company'];

        return $company['CompanyId'] === 'COMPANY-1'
            && $company['MainContact'] === 'Priya Nair'
            && $company['MainContactPhoneNumber'] === '01206555444'
            && $company['CompanyRegNumber'] === 'AB123456'
            && $company['VatRegNumber'] === 'GB123456789'
            && $company['Utr'] === '1234567890'
            && $company['BankName'] === 'Orbital Bank'
            && $company['AccountName'] === 'Orbital Umbrella Ltd'
            && $company['AccountNumber'] === '12345678'
            && $company['SortCode'] === '123456'
            && $company['Email'] === 'accounts@orbital.test'
            && $company['PhoneNumber'] === '01206555000';
    });
});

test('a PAYE candidate (no payment provider) sends their own bank details and no Company object', function () {
    $candidate = EducationCandidate::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_number' => '87654321',
        'bank_sort_code' => '654321',
    ]);

    (new UpsertCandidate(new EvertimeClient($this->company)))
        ->handle($candidate, 'CANDIDATE-1', null, null);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $data['WorkerType'] === 'Paye'
            && $data['AccountNumber'] === '87654321'
            && $data['SortCode'] === '654321'
            && ! array_key_exists('Company', $data);
    });
});

test('MainContact falls back to the company name when no contact name is set', function () {
    $paymentProvider = PaymentProvider::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Legacy Umbrella Ltd',
        'contact_first_name' => null,
        'contact_last_name' => null,
    ]);

    $candidate = EducationCandidate::factory()->create(['company_id' => $this->company->id]);

    (new UpsertCandidate(new EvertimeClient($this->company)))
        ->handle($candidate, 'CANDIDATE-1', $paymentProvider, 'COMPANY-1');

    Http::assertSent(fn ($request) => $request->data()['Company']['MainContact'] === 'Legacy Umbrella Ltd');
});
