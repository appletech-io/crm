<?php

use App\Filament\Resources\CompanyUsers\Pages\ListCompanyUsers;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

test('a non-admin cannot access the company users resource', function () {
    $user = User::factory()->create(['company_id' => $this->admin->company_id]);

    $this->actingAs($user)->get('/crm/company-users')->assertRedirect('/crm');
});

test('a site_admin cannot access the company users resource', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');

    $this->actingAs($siteAdmin)->get('/crm/company-users')->assertRedirect('/crm');
});

test('an admin can access the company users resource', function () {
    Livewire::test(ListCompanyUsers::class)->assertSuccessful();
});

test('the list excludes candidate and client portal accounts', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->admin->company_id]);
    $candidateUser = User::factory()->create([
        'company_id' => $this->admin->company_id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    $client = Client::factory()->create(['company_id' => $this->admin->company_id]);
    $contact = ClientContact::factory()->create([
        'company_id' => $this->admin->company_id,
        'client_id' => $client->id,
    ]);
    $clientUser = User::factory()->create([
        'company_id' => $this->admin->company_id,
        'client_contact_id' => $contact->id,
    ]);

    Livewire::test(ListCompanyUsers::class)
        ->assertCanSeeTableRecords([$this->admin])
        ->assertCanNotSeeTableRecords([$candidateUser, $clientUser]);
});

test('the list is scoped to the admins own company', function () {
    $otherCompany = Company::factory()->create();
    $otherCompanyUser = User::factory()->create(['company_id' => $otherCompany->id]);

    Livewire::test(ListCompanyUsers::class)
        ->assertCanSeeTableRecords([$this->admin])
        ->assertCanNotSeeTableRecords([$otherCompanyUser]);
});

test('an admin can assign an assignable role to a staff user', function () {
    $staff = User::factory()->create(['company_id' => $this->admin->company_id]);

    Livewire::test(ListCompanyUsers::class)
        ->callTableAction('manageRoles', $staff, data: ['roles' => ['consultant']]);

    expect($staff->fresh()->hasRole('consultant'))->toBeTrue();
});

test('an admin can remove an assignable role from a staff user', function () {
    $staff = User::factory()->create(['company_id' => $this->admin->company_id]);
    $staff->assignRole('consultant');

    Livewire::test(ListCompanyUsers::class)
        ->callTableAction('manageRoles', $staff, data: ['roles' => []]);

    expect($staff->fresh()->hasRole('consultant'))->toBeFalse();
});

test('managing roles rejects admin or site_admin even if submitted directly', function () {
    $staff = User::factory()->create(['company_id' => $this->admin->company_id]);

    Livewire::test(ListCompanyUsers::class)
        ->callTableAction('manageRoles', $staff, data: ['roles' => ['consultant', 'admin', 'site_admin']])
        ->assertHasTableActionErrors();

    $staff->refresh();

    expect($staff->hasRole('consultant'))->toBeFalse()
        ->and($staff->hasRole('admin'))->toBeFalse()
        ->and($staff->hasRole('site_admin'))->toBeFalse();
});

test('managing roles preserves an existing admin role that is not one of the assignable options', function () {
    $otherAdmin = User::factory()->create(['company_id' => $this->admin->company_id]);
    $otherAdmin->assignRole('admin');

    Livewire::test(ListCompanyUsers::class)
        ->callTableAction('manageRoles', $otherAdmin, data: ['roles' => ['resourcer']]);

    $otherAdmin->refresh();

    expect($otherAdmin->hasRole('admin'))->toBeTrue()
        ->and($otherAdmin->hasRole('resourcer'))->toBeTrue();
});

test('the compliance officer action is only shown for consultants', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');

    $resourcer = User::factory()->create(['company_id' => $this->admin->company_id]);
    $resourcer->assignRole('resourcer');

    Livewire::test(ListCompanyUsers::class)
        ->assertTableActionVisible('setComplianceOfficer', $consultant)
        ->assertTableActionHidden('setComplianceOfficer', $resourcer);
});

test('an admin can assign a compliance officer to a consultant', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');

    $complianceOfficer = User::factory()->create(['company_id' => $this->admin->company_id]);
    $complianceOfficer->assignRole('compliance');

    Livewire::test(ListCompanyUsers::class)
        ->callTableAction('setComplianceOfficer', $consultant, data: ['compliance_officer_id' => $complianceOfficer->id]);

    expect($consultant->fresh()->compliance_officer_id)->toBe($complianceOfficer->id);
});

test('an admin can clear a consultants compliance officer', function () {
    $complianceOfficer = User::factory()->create(['company_id' => $this->admin->company_id]);
    $complianceOfficer->assignRole('compliance');

    $consultant = User::factory()->create([
        'company_id' => $this->admin->company_id,
        'compliance_officer_id' => $complianceOfficer->id,
    ]);
    $consultant->assignRole('consultant');

    Livewire::test(ListCompanyUsers::class)
        ->callTableAction('setComplianceOfficer', $consultant, data: ['compliance_officer_id' => null]);

    expect($consultant->fresh()->compliance_officer_id)->toBeNull();
});

test('assigning a compliance officer rejects a user without the compliance role even if submitted directly', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');

    $notComplianceOfficer = User::factory()->create(['company_id' => $this->admin->company_id]);

    Livewire::test(ListCompanyUsers::class)
        ->callTableAction('setComplianceOfficer', $consultant, data: ['compliance_officer_id' => $notComplianceOfficer->id]);

    expect($consultant->fresh()->compliance_officer_id)->toBeNull();
});
