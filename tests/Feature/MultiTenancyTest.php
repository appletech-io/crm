<?php

use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use App\Models\EducationClient;

test('a user belongs to a company and can have multiple industries', function () {
    $company = Company::factory()->create();
    $industries = Industry::factory()->count(2)->create();

    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $user->industries()->attach($industries);

    expect($user->company->id)->toBe($company->id)
        ->and($user->industries)->toHaveCount(2);
});

test('a company can have multiple industries', function () {
    $company = Company::factory()->create();
    $industries = Industry::factory()->count(3)->create();

    $company->industries()->attach($industries);

    expect($company->industries)->toHaveCount(3);
});

test('an education client is scoped to the authenticated user company', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user = User::factory()->create(['company_id' => $company1->id]);
    $this->actingAs($user);

    $client1 = EducationClient::factory()->create(['company_id' => $company1->id]);
    $client2 = EducationClient::factory()->create(['company_id' => $company2->id]);

    $clients = EducationClient::all();

    expect($clients)->toHaveCount(1)
        ->and($clients->first()->id)->toBe($client1->id);
});
