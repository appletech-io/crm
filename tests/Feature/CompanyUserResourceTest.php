<?php

use App\Filament\Resources\CompanyUsers\Pages\EditCompanyUser;
use App\Filament\Resources\CompanyUsers\Pages\ListCompanyUsers;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\ConsultantKpiTarget;
use App\Models\EducationCandidate;
use App\Models\Industry;
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

test('editing a staff user preloads their existing assignable roles', function () {
    $staff = User::factory()->create(['company_id' => $this->admin->company_id]);
    $staff->assignRole(['consultant', 'resourcer']);

    Livewire::test(EditCompanyUser::class, ['record' => $staff->getRouteKey()])
        ->assertFormSet(['roles' => ['consultant', 'resourcer']]);
});

test('an admin can assign an assignable role to a staff user', function () {
    $staff = User::factory()->create(['company_id' => $this->admin->company_id]);

    Livewire::test(EditCompanyUser::class, ['record' => $staff->getRouteKey()])
        ->fillForm(['roles' => ['consultant']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($staff->fresh()->hasRole('consultant'))->toBeTrue();
});

test('an admin can remove an assignable role from a staff user', function () {
    $staff = User::factory()->create(['company_id' => $this->admin->company_id]);
    $staff->assignRole('consultant');

    Livewire::test(EditCompanyUser::class, ['record' => $staff->getRouteKey()])
        ->fillForm(['roles' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($staff->fresh()->hasRole('consultant'))->toBeFalse();
});

test('managing roles rejects admin or site_admin even if submitted directly', function () {
    $staff = User::factory()->create(['company_id' => $this->admin->company_id]);

    Livewire::test(EditCompanyUser::class, ['record' => $staff->getRouteKey()])
        ->fillForm(['roles' => ['consultant', 'admin', 'site_admin']])
        ->call('save')
        ->assertHasFormErrors();

    $staff->refresh();

    expect($staff->hasRole('consultant'))->toBeFalse()
        ->and($staff->hasRole('admin'))->toBeFalse()
        ->and($staff->hasRole('site_admin'))->toBeFalse();
});

test('managing roles preserves an existing admin role that is not one of the assignable options', function () {
    $otherAdmin = User::factory()->create(['company_id' => $this->admin->company_id]);
    $otherAdmin->assignRole('admin');

    Livewire::test(EditCompanyUser::class, ['record' => $otherAdmin->getRouteKey()])
        ->fillForm(['roles' => ['resourcer']])
        ->call('save')
        ->assertHasNoFormErrors();

    $otherAdmin->refresh();

    expect($otherAdmin->hasRole('admin'))->toBeTrue()
        ->and($otherAdmin->hasRole('resourcer'))->toBeTrue();
});

test('the compliance officer field is only shown for consultants', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');

    $resourcer = User::factory()->create(['company_id' => $this->admin->company_id]);
    $resourcer->assignRole('resourcer');

    Livewire::test(EditCompanyUser::class, ['record' => $consultant->getRouteKey()])
        ->assertFormFieldIsVisible('compliance_officer_id');

    Livewire::test(EditCompanyUser::class, ['record' => $resourcer->getRouteKey()])
        ->assertFormFieldIsHidden('compliance_officer_id');
});

test('an admin can assign a compliance officer to a consultant', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');

    $complianceOfficer = User::factory()->create(['company_id' => $this->admin->company_id]);
    $complianceOfficer->assignRole('compliance');

    Livewire::test(EditCompanyUser::class, ['record' => $consultant->getRouteKey()])
        ->fillForm(['roles' => ['consultant'], 'compliance_officer_id' => $complianceOfficer->id])
        ->call('save')
        ->assertHasNoFormErrors();

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

    Livewire::test(EditCompanyUser::class, ['record' => $consultant->getRouteKey()])
        ->fillForm(['roles' => ['consultant'], 'compliance_officer_id' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($consultant->fresh()->compliance_officer_id)->toBeNull();
});

test('assigning a compliance officer rejects a user without the compliance role even if submitted directly', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');

    $notComplianceOfficer = User::factory()->create(['company_id' => $this->admin->company_id]);

    Livewire::test(EditCompanyUser::class, ['record' => $consultant->getRouteKey()])
        ->fillForm(['roles' => ['consultant'], 'compliance_officer_id' => $notComplianceOfficer->id])
        ->call('save')
        ->assertHasFormErrors(['compliance_officer_id']);
});

test('the KPI targets field is only shown for consultants', function () {
    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');

    $resourcer = User::factory()->create(['company_id' => $this->admin->company_id]);
    $resourcer->assignRole('resourcer');

    Livewire::test(EditCompanyUser::class, ['record' => $consultant->getRouteKey()])
        ->assertFormFieldIsVisible('kpiTargets');

    Livewire::test(EditCompanyUser::class, ['record' => $resourcer->getRouteKey()])
        ->assertFormFieldIsHidden('kpiTargets');
});

test('an admin can set a consultants KPI targets for a sector', function () {
    $industry = Industry::factory()->create();
    $this->admin->company->industries()->attach($industry);

    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');

    Livewire::test(EditCompanyUser::class, ['record' => $consultant->getRouteKey()])
        ->fillForm([
            'roles' => ['consultant'],
            'kpiTargets' => [
                'item-1' => [
                    'industry_id' => $industry->id,
                    'gp_target' => 2000,
                    'candidate_days_target' => 40,
                    'working_candidates_target' => 15,
                    'clients_booked_target' => 8,
                    'rebook_rate_target' => 75.5,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $target = ConsultantKpiTarget::where('user_id', $consultant->id)->where('industry_id', $industry->id)->first();

    expect($target)->not->toBeNull()
        ->and($target->gp_target)->toBe(2000)
        ->and($target->candidate_days_target)->toBe(40)
        ->and($target->working_candidates_target)->toBe(15)
        ->and($target->clients_booked_target)->toBe(8)
        ->and($target->rebook_rate_target)->toBe(75.5);
});

test('editing a consultants KPI targets replaces removed sectors rather than leaving them stale', function () {
    $industry = Industry::factory()->create();
    $otherIndustry = Industry::factory()->create();
    $this->admin->company->industries()->attach([$industry->id, $otherIndustry->id]);

    $consultant = User::factory()->create(['company_id' => $this->admin->company_id]);
    $consultant->assignRole('consultant');

    ConsultantKpiTarget::factory()->create([
        'user_id' => $consultant->id,
        'industry_id' => $industry->id,
        'gp_target' => 1000,
    ]);

    Livewire::test(EditCompanyUser::class, ['record' => $consultant->getRouteKey()])
        ->set('data.kpiTargets', [
            'item-1' => [
                'industry_id' => $otherIndustry->id,
                'gp_target' => 3000,
                'candidate_days_target' => null,
                'working_candidates_target' => null,
                'clients_booked_target' => null,
                'rebook_rate_target' => null,
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ConsultantKpiTarget::where('user_id', $consultant->id)->where('industry_id', $industry->id)->exists())->toBeFalse()
        ->and(ConsultantKpiTarget::where('user_id', $consultant->id)->where('industry_id', $otherIndustry->id)->first()?->gp_target)->toBe(3000);
});
