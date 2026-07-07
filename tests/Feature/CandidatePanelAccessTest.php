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

test('a candidate cannot access the admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('candidate');

    $this->actingAs($user)->get('/crm')->assertForbidden();
});

test('a staff user cannot access the candidate panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/candidate')->assertForbidden();
});
