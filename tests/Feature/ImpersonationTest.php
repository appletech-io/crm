<?php

use App\Filament\Pages\RunPayroll;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->siteAdmin = User::factory()->create();
    $this->siteAdmin->assignRole('site_admin');
    $this->actingAs($this->siteAdmin);
});

test('site_admin can view a company as one of its admin users', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    Livewire::test(ListCompanies::class)
        ->callTableAction('viewAs', $company, data: ['user_id' => $admin->id])
        ->assertRedirect('/crm');

    expect(auth()->id())->toBe($admin->id)
        ->and(session('impersonator_id'))->toBe($this->siteAdmin->id);
});

test('site_admin can view a company as one of its consultant users', function () {
    $company = Company::factory()->create();
    $consultant = User::factory()->create(['company_id' => $company->id]);
    $consultant->assignRole('consultant');

    Livewire::test(ListCompanies::class)
        ->callTableAction('viewAs', $company, data: ['user_id' => $consultant->id])
        ->assertRedirect('/crm');

    expect(auth()->id())->toBe($consultant->id);
});

test('a site_admin cannot access run payroll until impersonating an admin', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    expect(RunPayroll::canAccess())->toBeFalse();

    Livewire::test(ListCompanies::class)
        ->callTableAction('viewAs', $company, data: ['user_id' => $admin->id]);

    expect(RunPayroll::canAccess())->toBeTrue();
});

test('the view as action is disabled for a company with no admin or consultant users', function () {
    $company = Company::factory()->create();

    Livewire::test(ListCompanies::class)
        ->assertTableActionDisabled('viewAs', $company);
});

test('viewing as a user with an ineligible role is rejected even if submitted directly', function () {
    $company = Company::factory()->create();
    $resourcer = User::factory()->create(['company_id' => $company->id]);
    $resourcer->assignRole('resourcer');

    Livewire::test(ListCompanies::class)
        ->callTableAction('viewAs', $company, data: ['user_id' => $resourcer->id]);

    expect(auth()->id())->toBe($this->siteAdmin->id)
        ->and(session()->has('impersonator_id'))->toBeFalse();
});

test('viewing as an admin from a different company is rejected even if submitted directly', function () {
    $company = Company::factory()->create();
    $otherCompanyAdmin = User::factory()->create();
    $otherCompanyAdmin->assignRole('admin');

    Livewire::test(ListCompanies::class)
        ->callTableAction('viewAs', $company, data: ['user_id' => $otherCompanyAdmin->id]);

    expect(auth()->id())->toBe($this->siteAdmin->id)
        ->and(session()->has('impersonator_id'))->toBeFalse();
});

test('exiting impersonation restores the original site_admin session', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin);

    $this->withSession(['impersonator_id' => $this->siteAdmin->id])
        ->post('/impersonate/stop')
        ->assertRedirect('/crm/companies');

    expect(auth()->id())->toBe($this->siteAdmin->id)
        ->and(session()->has('impersonator_id'))->toBeFalse();
});

test('exiting impersonation does nothing harmful when there is no impersonation in progress', function () {
    $this->post('/impersonate/stop');

    expect(auth()->id())->toBe($this->siteAdmin->id);
});

test('a non-site_admin cannot access the companies resource at all', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $this->get('/crm/companies')->assertRedirect('/crm');
});
