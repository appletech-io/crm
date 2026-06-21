<?php

use App\Filament\Widgets\NoIndustryWidget;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use App\Filament\Resources\EducationClients\EducationClientResource;
use App\Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Auth;

test('education client resource is hidden for user without company', function () {
    $user = User::factory()->create(['company_id' => null]);
    Auth::login($user);

    expect(EducationClientResource::canViewAny())->toBeFalse();
});

test('education client resource is hidden for user whose company lacks education industry', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    Auth::login($user);

    expect(EducationClientResource::canViewAny())->toBeFalse();
});

test('education client resource is visible for user whose company has education industry', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $company = Company::factory()->create();
    $company->industries()->attach($educationIndustry);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->industries()->attach($educationIndustry);
    Auth::login($user);

    expect(EducationClientResource::canViewAny())->toBeTrue();
});

test('education client resource is hidden for user whose company has education industry but user does not', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $company = Company::factory()->create();
    $company->industries()->attach($educationIndustry);

    $user = User::factory()->create(['company_id' => $company->id]);
    // User does NOT have the industry attached
    Auth::login($user);

    expect(EducationClientResource::canViewAny())->toBeFalse();
});

test('education dashboard is visible for user whose only industry is education', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $company = Company::factory()->create();
    $company->industries()->attach($educationIndustry);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->industries()->attach($educationIndustry);
    Auth::login($user);

    $dashboard = new Dashboard;
    expect($dashboard->getTitle())->toBe('Education Dashboard')
        ->and($dashboard->getWidgets())->not->toBeEmpty();
});

test('education dashboard is hidden for user with multiple industries and invalid industry selected', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $otherIndustry = Industry::factory()->create(['slug' => 'other']);
    $company = Company::factory()->create();
    $company->industries()->attach([$educationIndustry->id, $otherIndustry->id]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->industries()->attach([$educationIndustry->id, $otherIndustry->id]);
    Auth::login($user);

    config(['user.industry' => 'invalid']);

    $dashboard = new Dashboard;
    expect($dashboard->getActiveIndustry())->toBeNull()
        ->and($dashboard->getTitle())->toBe('Dashboard')
        ->and($dashboard->getWidgets())->toBe([NoIndustryWidget::class]);
});

test('education dashboard is visible for user with multiple industries and education selected', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $otherIndustry = Industry::factory()->create(['slug' => 'other']);
    $company = Company::factory()->create();
    $company->industries()->attach([$educationIndustry->id, $otherIndustry->id]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->industries()->attach([$educationIndustry->id, $otherIndustry->id]);
    Auth::login($user);

    config(['user.industry' => 'education']);

    $dashboard = new Dashboard;
    expect($dashboard->getTitle())->toBe('Education Dashboard')
        ->and($dashboard->getWidgets())->not->toBeEmpty();
});

test('no industry dashboard is visible for user without industries', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $dashboard = new Dashboard;
    expect($dashboard->getTitle())->toBe('Dashboard')
        ->and($dashboard->getWidgets())->toBe([NoIndustryWidget::class]);
});

test('no industry dashboard is hidden for user with industries', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $company = Company::factory()->create();
    $company->industries()->attach($educationIndustry);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->industries()->attach($educationIndustry);
    Auth::login($user);

    $dashboard = new Dashboard;
    expect($dashboard->getTitle())->toBe('Education Dashboard')
        ->and($dashboard->getWidgets())->not->toBeEmpty();
});

test('multi-industry user with NO config set falls back to first industry (Dashboard integration check)', function () {
    $educationIndustry = Industry::factory()->create(['slug' => 'education']);
    $otherIndustry = Industry::factory()->create(['slug' => 'other']);
    $company = Company::factory()->create();
    $company->industries()->attach([$educationIndustry->id, $otherIndustry->id]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->industries()->attach([$educationIndustry->id, $otherIndustry->id]);
    Auth::login($user);

    // config('user.industry') is NOT set (null)
    $dashboard = new Dashboard;
    expect($dashboard->getTitle())->toBe('Education Dashboard')
        ->and($dashboard->getWidgets())->not->toBeEmpty();
});
