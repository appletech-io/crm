<?php

use App\Filament\Pages\Analytics\VacanciesReport;
use App\Filament\Pages\JobPipeline;
use App\Filament\Resources\Vacancies\VacancyResource;
use App\Models\CompanyIndustry;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->company = $this->admin->company;
    $this->industry = Industry::factory()->create(['slug' => 'it']);
    $this->company->industries()->attach($this->industry->id);

    Cache::put("user.{$this->admin->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $this->industry->id);
});

test('active_industry_uses_perm defaults to true when no company_industry row matches', function () {
    $otherIndustry = Industry::factory()->create(['slug' => 'other']);
    Cache::put("user.{$this->admin->id}.active_industry", $otherIndustry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $otherIndustry->id);

    expect(active_industry_uses_perm())->toBeTrue();
});

test('active_industry_uses_perm defaults to true when no user is authenticated', function () {
    auth()->logout();

    expect(active_industry_uses_perm())->toBeTrue();
});

test('active_industry_uses_perm reflects the company_industry pivot flag', function () {
    expect(active_industry_uses_perm())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_perm' => false]);

    expect(active_industry_uses_perm())->toBeFalse();
});

test('Job Pipeline is hidden when the active industry has perm switched off', function () {
    expect(JobPipeline::canAccess())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_perm' => false]);

    expect(JobPipeline::canAccess())->toBeFalse();
});

test('Jobs (VacancyResource) is hidden when the active industry has perm switched off', function () {
    expect(VacancyResource::canViewAny())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_perm' => false]);

    expect(VacancyResource::canViewAny())->toBeFalse();
});

test('Vacancies & Placements report is hidden when the active industry has perm switched off', function () {
    expect(VacanciesReport::canAccess())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_perm' => false]);

    expect(VacanciesReport::canAccess())->toBeFalse();
});

test('a different industry on the same company with perm still on is unaffected', function () {
    $permIndustry = Industry::factory()->create(['slug' => 'construction']);
    $this->company->industries()->attach($permIndustry->id);

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_perm' => false]);

    Cache::put("user.{$this->admin->id}.active_industry", $permIndustry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $permIndustry->id);

    expect(active_industry_uses_perm())->toBeTrue()
        ->and(JobPipeline::canAccess())->toBeTrue();
});

test('uses_bookings and uses_perm are independent flags', function () {
    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_bookings' => false]);

    expect(active_industry_uses_bookings())->toBeFalse()
        ->and(active_industry_uses_perm())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_bookings' => true, 'uses_perm' => false]);

    expect(active_industry_uses_bookings())->toBeTrue()
        ->and(active_industry_uses_perm())->toBeFalse();
});
