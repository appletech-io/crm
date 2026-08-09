<?php

use App\Filament\Resources\PortalAccounts\Pages\ListPortalAccounts;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->education = Industry::factory()->create(['slug' => 'education']);
    $this->healthcare = Industry::factory()->create(['slug' => 'healthcare']);

    Cache::put("user.{$this->admin->id}.active_industry", $this->education->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->education->id);
});

test('a non-admin cannot access the portal accounts resource', function () {
    $user = User::factory()->create(['company_id' => $this->admin->company_id]);

    $this->actingAs($user)->get('/crm/portal-accounts')->assertRedirect('/crm');
});

test('an admin with no active sector cannot access the portal accounts resource', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/crm/portal-accounts')->assertRedirect('/crm');
});

test('an admin can access the portal accounts resource', function () {
    Livewire::test(ListPortalAccounts::class)->assertSuccessful();
});

test('the list shows a candidate portal login for the active sector', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->admin->company_id]);
    $candidateUser = User::factory()->create([
        'company_id' => $this->admin->company_id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    Livewire::test(ListPortalAccounts::class)
        ->assertCanSeeTableRecords([$candidateUser]);
});

test('the list excludes candidate portal logins for a different sector', function () {
    $healthcareCandidate = HealthcareCandidate::factory()->create(['company_id' => $this->admin->company_id]);
    $healthcareUser = User::factory()->create([
        'company_id' => $this->admin->company_id,
        'candidate_id' => $healthcareCandidate->id,
        'candidate_type' => HealthcareCandidate::class,
    ]);

    Livewire::test(ListPortalAccounts::class)
        ->assertCanNotSeeTableRecords([$healthcareUser]);
});

test('the list shows a client portal login for the active sector', function () {
    $client = Client::factory()->create([
        'company_id' => $this->admin->company_id,
        'industry_id' => $this->education->id,
    ]);
    $contact = ClientContact::factory()->create([
        'company_id' => $this->admin->company_id,
        'client_id' => $client->id,
    ]);
    $clientUser = User::factory()->create([
        'company_id' => $this->admin->company_id,
        'client_contact_id' => $contact->id,
    ]);

    Livewire::test(ListPortalAccounts::class)
        ->assertCanSeeTableRecords([$clientUser]);
});

test('the list excludes client portal logins for a different sector', function () {
    $client = Client::factory()->create([
        'company_id' => $this->admin->company_id,
        'industry_id' => $this->healthcare->id,
    ]);
    $contact = ClientContact::factory()->create([
        'company_id' => $this->admin->company_id,
        'client_id' => $client->id,
    ]);
    $clientUser = User::factory()->create([
        'company_id' => $this->admin->company_id,
        'client_contact_id' => $contact->id,
    ]);

    Livewire::test(ListPortalAccounts::class)
        ->assertCanNotSeeTableRecords([$clientUser]);
});

test('the list excludes staff users and users from other companies', function () {
    $staff = User::factory()->create(['company_id' => $this->admin->company_id]);

    $otherCompany = Company::factory()->create();
    $otherCandidate = EducationCandidate::factory()->create(['company_id' => $otherCompany->id]);
    $otherCompanyCandidateUser = User::factory()->create([
        'company_id' => $otherCompany->id,
        'candidate_id' => $otherCandidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);

    Livewire::test(ListPortalAccounts::class)
        ->assertCanNotSeeTableRecords([$staff, $otherCompanyCandidateUser]);
});

test('an admin can reset a candidate portal accounts password', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->admin->company_id]);
    $candidateUser = User::factory()->create([
        'company_id' => $this->admin->company_id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
        'password_changed_at' => now(),
    ]);

    Livewire::test(ListPortalAccounts::class)
        ->callTableAction('resetPassword', $candidateUser, data: [
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

    $candidateUser->refresh();

    expect(Hash::check('a-brand-new-password', $candidateUser->password))->toBeTrue()
        ->and($candidateUser->requires_account_setup)->toBeTrue()
        ->and($candidateUser->password_changed_at)->toBeNull();
});
