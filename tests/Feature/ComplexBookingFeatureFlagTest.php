<?php

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

test('active_industry_uses_complex_booking defaults to false when no company_industry row matches', function () {
    $otherIndustry = Industry::factory()->create(['slug' => 'other']);
    Cache::put("user.{$this->admin->id}.active_industry", $otherIndustry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $otherIndustry->id);

    expect(active_industry_uses_complex_booking())->toBeFalse();
});

test('active_industry_uses_complex_booking defaults to false when no user is authenticated', function () {
    auth()->logout();

    expect(active_industry_uses_complex_booking())->toBeFalse();
});

test('CompanyIndustry::usesComplexBooking defaults to false when companyId/industryId cannot be resolved', function () {
    expect(CompanyIndustry::usesComplexBooking(null, null))->toBeFalse()
        ->and(CompanyIndustry::usesComplexBooking($this->company->id, null))->toBeFalse()
        ->and(CompanyIndustry::usesComplexBooking(null, $this->industry->id))->toBeFalse();
});

test('active_industry_uses_complex_booking reflects the company_industry pivot flag', function () {
    expect(active_industry_uses_complex_booking())->toBeFalse();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['complex_booking' => true]);

    expect(active_industry_uses_complex_booking())->toBeTrue();
});

test('a different industry on the same company with complex booking still off is unaffected', function () {
    $otherIndustry = Industry::factory()->create(['slug' => 'construction']);
    $this->company->industries()->attach($otherIndustry->id);

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['complex_booking' => true]);

    Cache::put("user.{$this->admin->id}.active_industry", $otherIndustry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $otherIndustry->id);

    expect(active_industry_uses_complex_booking())->toBeFalse();
});

test('complex_booking is independent of uses_bookings and uses_perm', function () {
    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['complex_booking' => true]);

    expect(active_industry_uses_complex_booking())->toBeTrue()
        ->and(active_industry_uses_bookings())->toBeTrue()
        ->and(active_industry_uses_perm())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_bookings' => false, 'uses_perm' => false]);

    expect(active_industry_uses_complex_booking())->toBeTrue();
});
