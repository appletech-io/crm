<?php

use App\Filament\Pages\RunPayroll;
use App\Filament\Pages\ViewPayroll;
use App\Filament\Resources\Bookings\BookingResource;
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

test('active_industry_uses_bookings defaults to true when no company_industry row matches', function () {
    // The beforeEach attach() above leaves uses_bookings at its column
    // default (true) — this covers the case where the row genuinely
    // doesn't exist at all, by pointing at an industry the company was
    // never attached to.
    $otherIndustry = Industry::factory()->create(['slug' => 'other']);
    Cache::put("user.{$this->admin->id}.active_industry", $otherIndustry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $otherIndustry->id);

    expect(active_industry_uses_bookings())->toBeTrue();
});

test('active_industry_uses_bookings defaults to true when no user is authenticated', function () {
    auth()->logout();

    expect(active_industry_uses_bookings())->toBeTrue();
});

test('active_industry_uses_bookings reflects the company_industry pivot flag', function () {
    expect(active_industry_uses_bookings())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_bookings' => false]);

    expect(active_industry_uses_bookings())->toBeFalse();
});

test('BookingResource is hidden when the active industry has bookings switched off', function () {
    expect(BookingResource::canViewAny())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_bookings' => false]);

    expect(BookingResource::canViewAny())->toBeFalse();
});

test('Run Payroll is hidden when the active industry has bookings switched off', function () {
    expect(RunPayroll::canAccess())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_bookings' => false]);

    expect(RunPayroll::canAccess())->toBeFalse();
});

test('Timesheets (ViewPayroll) is hidden when the active industry has bookings switched off', function () {
    expect(ViewPayroll::canAccess())->toBeTrue();

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_bookings' => false]);

    expect(ViewPayroll::canAccess())->toBeFalse();
});

test('a different industry on the same company with bookings still on is unaffected', function () {
    $bookingIndustry = Industry::factory()->create(['slug' => 'construction']);
    $this->company->industries()->attach($bookingIndustry->id);

    CompanyIndustry::where('company_id', $this->company->id)
        ->where('industry_id', $this->industry->id)
        ->update(['uses_bookings' => false]);

    Cache::put("user.{$this->admin->id}.active_industry", $bookingIndustry->slug);
    Cache::put("user.{$this->admin->id}.active_industry_id", $bookingIndustry->id);

    expect(active_industry_uses_bookings())->toBeTrue()
        ->and(BookingResource::canViewAny())->toBeTrue();
});
