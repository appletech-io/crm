<?php

use App\Enums\BookingDayPeriod;
use App\Enums\Integration;
use App\Enums\PaymentMethod;
use App\Filament\Pages\RunPayroll;
use App\Filament\Support\ExportPayrollCsvAction;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\PaymentProvider;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/** @return array<string, string> */
function csvRowFor(BookingDay $dayPeriod): array
{
    $period = ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()];
    $response = ExportPayrollCsvAction::download(collect([$dayPeriod]), $period);

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    $rows = array_map('str_getcsv', explode("\n", trim($csv)));

    return array_combine($rows[0], $rows[1]);
}

test('a PAYE candidate\'s row includes their full details, own bank details, and no umbrella company', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id, 'name' => 'Oakwood School', 'phone' => '01213334444']);
    ClientContact::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'main_contact' => true,
        'first_name' => 'Pat',
        'last_name' => 'Jones',
        'email' => 'pat.jones@oakwood.test',
    ]);
    $jobTitle = JobTitle::factory()->create(['company_id' => $company->id, 'name' => 'Teacher']);
    $consultant = User::factory()->create(['company_id' => $company->id, 'name' => 'Kirsty Greaves']);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'ni_number' => 'QQ123456C',
        'date_of_birth' => '1990-05-04',
        'gender' => 'female',
        'email' => 'jane.doe@example.test',
        'mobile' => '07700900000',
        'phone' => '01213330000',
        'address' => '1 Elm Street',
        'city' => 'Birmingham',
        'county' => 'West Midlands',
        'postcode' => 'B1 1AA',
        'payment_method' => PaymentMethod::Paye->value,
        'bank_account_number' => '87654321',
        'bank_sort_code' => '654321',
    ]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
        'consultant_id' => $consultant->id,
        'day_rate' => 150.00,
        'day_charge_rate' => 200.00,
    ]);

    $dayPeriod = $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
        'approved_at' => now(),
    ]);

    $row = csvRowFor($dayPeriod->fresh());

    expect($row['Candidate'])->toBe('Jane Doe')
        ->and($row['First Name'])->toBe('Jane')
        ->and($row['Last Name'])->toBe('Doe')
        ->and($row['Date of Birth'])->toBe('1990-05-04')
        ->and($row['Gender'])->toBe('female')
        ->and($row['NI Number'])->toBe('QQ123456C')
        ->and($row['Email'])->toBe('jane.doe@example.test')
        ->and($row['Mobile'])->toBe('07700900000')
        ->and($row['Home Phone'])->toBe('01213330000')
        ->and($row['Address'])->toBe('1 Elm Street')
        ->and($row['City'])->toBe('Birmingham')
        ->and($row['County'])->toBe('West Midlands')
        ->and($row['Postcode'])->toBe('B1 1AA')
        ->and($row['Client'])->toBe('Oakwood School')
        ->and($row['Client Phone'])->toBe('01213334444')
        ->and($row['Client Contact'])->toBe('Pat Jones')
        ->and($row['Client Contact Email'])->toBe('pat.jones@oakwood.test')
        ->and($row['Job Title'])->toBe('Teacher')
        ->and($row['Consultant'])->toBe('Kirsty Greaves')
        ->and($row['Pay Rate'])->toBe('150')
        ->and($row['Charge Rate'])->toBe('200')
        ->and($row['Status'])->toBe('approved')
        ->and($row['Payment Method'])->toBe('PAYE')
        ->and($row['Umbrella Company'])->toBe('')
        ->and($row['Bank Account Number'])->toBe('87654321')
        ->and($row['Sort Code'])->toBe('654321');
});

test('an umbrella candidate\'s row shows the umbrella company\'s full profile and bank details, not their own', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $jobTitle = JobTitle::factory()->create(['company_id' => $company->id]);

    $paymentProvider = PaymentProvider::factory()->create([
        'company_id' => $company->id,
        'name' => 'Orbital Umbrella Ltd',
        'address_1' => '1 Orbital Way',
        'address_2' => 'Suite 2',
        'county' => 'Greater London',
        'postcode' => 'EC1A 1AA',
        'company_reg_number' => 'AB123456',
        'vat_reg_number' => 'GB123456789',
        'email' => 'accounts@orbital.test',
        'bank_account_name' => 'Orbital Umbrella Ltd',
        'bank_account_number' => '11112222',
        'bank_sort_code' => '112233',
    ]);

    $candidate = EducationCandidate::factory()->create([
        'company_id' => $company->id,
        'payment_method' => PaymentMethod::Umbrella->value,
        'payment_provider_id' => $paymentProvider->id,
        'bank_account_number' => '99998888',
        'bank_sort_code' => '998877',
    ]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'job_title_id' => $jobTitle->id,
    ]);

    $dayPeriod = $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
    ]);

    $row = csvRowFor($dayPeriod->fresh());

    expect($row['Payment Method'])->toBe('Umbrella')
        ->and($row['Umbrella Company'])->toBe('Orbital Umbrella Ltd')
        ->and($row['Umbrella Address'])->toBe('1 Orbital Way, Suite 2')
        ->and($row['Umbrella County'])->toBe('Greater London')
        ->and($row['Umbrella Postcode'])->toBe('EC1A 1AA')
        ->and($row['Umbrella Reg Number'])->toBe('AB123456')
        ->and($row['Umbrella VAT Number'])->toBe('GB123456789')
        ->and($row['Umbrella Email'])->toBe('accounts@orbital.test')
        ->and($row['Bank Account Name'])->toBe('Orbital Umbrella Ltd')
        ->and($row['Bank Account Number'])->toBe('11112222')
        ->and($row['Sort Code'])->toBe('112233');
});

test('a cancelled day has no pay or charge rate in its row', function () {
    $company = Company::factory()->create();
    $client = Client::factory()->create(['company_id' => $company->id]);
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);

    $booking = Booking::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'day_rate' => 150.00,
        'day_charge_rate' => 200.00,
    ]);

    $dayPeriod = $booking->dayPeriods()->create([
        'company_id' => $company->id,
        'date' => now()->toDateString(),
        'period' => BookingDayPeriod::FullDay,
        'cancelled_at' => now(),
    ]);

    $row = csvRowFor($dayPeriod->fresh());

    expect($row['Pay Rate'])->toBe('')
        ->and($row['Charge Rate'])->toBe('');
});

test('the export csv button shows on the run payroll page regardless of whether the company has a payroll provider configured', function () {
    $companyWithoutProvider = Company::factory()->create(['payroll_provider' => null]);
    $companyWithProvider = Company::factory()->create(['payroll_provider' => Integration::Evertime->value]);

    seedRunPayrollAdmin($companyWithoutProvider)
        ->assertTableActionVisible('exportPayrollCsv');

    seedRunPayrollAdmin($companyWithProvider)
        ->assertTableActionVisible('exportPayrollCsv');
});

function seedRunPayrollAdmin(Company $company)
{
    test()->seed(RoleSeeder::class);
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');
    test()->actingAs($admin);
    Cache::put("user.{$admin->id}.active_industry", 'education');
    Cache::put("user.{$admin->id}.active_industry_id", 1);
    TimesheetPeriod::current($company);

    return Livewire::test(RunPayroll::class);
}
