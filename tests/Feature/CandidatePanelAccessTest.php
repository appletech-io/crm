<?php

use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a candidate can access the candidate panel', function () {
    $company = Company::factory()->create();
    $candidate = EducationCandidate::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create([
        'company_id' => $company->id,
        'candidate_id' => $candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $user->assignRole('candidate');

    $this->actingAs($user)->get('/candidate/documents')->assertOk();
});

test('a candidate is redirected to the candidate panel instead of hitting a 403 on the admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('candidate');

    $this->actingAs($user)->get('/crm')->assertRedirect('/candidate');
});

test('a candidate is redirected away from a deeper admin panel url too', function () {
    $user = User::factory()->create();
    $user->assignRole('candidate');

    $this->actingAs($user)->get('/crm/education-candidates')->assertRedirect('/candidate');
});

test('a staff user is redirected to the admin panel instead of hitting a 403 on the candidate panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/candidate')->assertRedirect('/crm');
});

test('a staff user is redirected away from a deeper candidate panel url too', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/candidate/documents')->assertRedirect('/crm');
});
