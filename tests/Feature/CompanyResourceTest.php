<?php

use App\Enums\EmailProvider;
use App\Enums\TimesheetFrequency;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('site_admin');
    $this->actingAs($this->user);
});

test('microsoft section is visible when email provider is microsoft', function () {
    $company = Company::factory()->create(['email_provider' => EmailProvider::Microsoft]);

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->assertFormFieldExists('ms_tenant_id')
        ->assertFormFieldIsVisible('ms_tenant_id');
});

test('microsoft section is hidden when email provider is mailgun', function () {
    $company = Company::factory()->create(['email_provider' => EmailProvider::Mailgun]);

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->assertFormFieldIsHidden('ms_tenant_id');
});

test('microsoft credentials can be saved and the client secret is encrypted at rest', function () {
    $company = Company::factory()->create(['email_provider' => EmailProvider::Microsoft]);

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->fillForm([
            'ms_tenant_id' => 'tenant-123',
            'ms_client_id' => 'client-456',
            'ms_client_secret' => 'super-secret',
            'ms_sender_email' => 'sender@example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $company->refresh();

    expect($company->ms_tenant_id)->toBe('tenant-123')
        ->and($company->ms_client_id)->toBe('client-456')
        ->and($company->ms_client_secret)->toBe('super-secret')
        ->and($company->getRawOriginal('ms_client_secret'))->not->toBe('super-secret');
});

test('legal details can be saved via the edit page', function () {
    $company = Company::factory()->create(['email_provider' => EmailProvider::Mailgun]);

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->fillForm([
            'trading_name' => 'Applebough Recruitment',
            'legal_name' => 'Applebough Group Ltd',
            'company_number' => '12345678',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $company->fresh();

    expect($fresh->trading_name)->toBe('Applebough Recruitment')
        ->and($fresh->legal_name)->toBe('Applebough Group Ltd')
        ->and($fresh->company_number)->toBe('12345678');
});

test('a logo can be uploaded via the edit page', function () {
    Storage::fake('local');

    $company = Company::factory()->create(['email_provider' => EmailProvider::Mailgun]);
    $file = UploadedFile::fake()->image('logo.png');

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->fillForm(['logo' => $file])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $company->fresh();

    expect($fresh->logo)->not->toBeNull();
    Storage::disk('local')->assertExists($fresh->logo);
});

test('a company defaults to weekly timesheet frequency', function () {
    $company = Company::factory()->create();

    expect($company->timesheet_frequency)->toBe(TimesheetFrequency::Weekly);
});

test('the day of month field is only visible and required when monthly frequency is selected', function () {
    $company = Company::factory()->create();

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->assertFormFieldIsHidden('timesheet_day_of_month')
        ->set('data.timesheet_frequency', 'monthly')
        ->assertFormFieldIsVisible('timesheet_day_of_month')
        ->fillForm(['timesheet_day_of_month' => null])
        ->call('save')
        ->assertHasFormErrors(['timesheet_day_of_month']);
});

test('timesheet frequency and day of month can be saved via the edit page', function () {
    $company = Company::factory()->create(['email_provider' => EmailProvider::Mailgun]);

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->fillForm([
            'timesheet_frequency' => 'monthly',
            'timesheet_day_of_month' => 15,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $company->fresh();

    expect($fresh->timesheet_frequency)->toBe(TimesheetFrequency::Monthly)
        ->and($fresh->timesheet_day_of_month)->toBe(15);
});
