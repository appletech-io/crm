<?php

use App\Filament\Resources\Companies\Pages\ManageCompanyFeatures;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('site_admin');
    $this->actingAs($this->user);
});

test('the features page renders successfully', function () {
    $company = Company::factory()->create();

    Livewire::test(ManageCompanyFeatures::class, ['record' => $company->getRouteKey()])
        ->assertSuccessful();
});

test('the features page loads an existing sector with its uses_bookings value', function () {
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'it']);
    $company->industries()->attach($industry->id, ['uses_bookings' => false]);

    // Filament keys a relationship-backed repeater's loaded rows by the
    // pivot record's own id (e.g. "record-1"), not sequential integers, so
    // this checks the single row's values directly rather than the exact
    // array shape assertFormSet() would otherwise require.
    $rows = Livewire::test(ManageCompanyFeatures::class, ['record' => $company->getRouteKey()])
        ->get('data.companyIndustries');

    expect($rows)->toHaveCount(1)
        ->and(collect($rows)->first())->toMatchArray([
            'industry_id' => $industry->id,
            'uses_bookings' => false,
        ]);
});

test('the features page shows one row per sector the company already has', function () {
    $company = Company::factory()->create();
    $construction = Industry::factory()->create(['slug' => 'construction']);
    $it = Industry::factory()->create(['slug' => 'it']);
    $company->industries()->attach([$construction->id, $it->id]);

    $rows = Livewire::test(ManageCompanyFeatures::class, ['record' => $company->getRouteKey()])
        ->get('data.companyIndustries');

    expect(collect($rows)->pluck('industry_id')->map(fn ($id) => (int) $id)->sort()->values()->all())
        ->toBe(collect([$construction->id, $it->id])->sort()->values()->all());
});

test('uses_bookings can be turned off for a sector via the features page', function () {
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'it']);
    $company->industries()->attach($industry->id);

    Livewire::test(ManageCompanyFeatures::class, ['record' => $company->getRouteKey()])
        ->fillForm([
            'companyIndustries' => [
                ['industry_id' => $industry->id, 'uses_bookings' => false],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($company->industries()->first()->pivot->uses_bookings)->toBeFalse();
});

test('uses_bookings can be turned back on for a sector via the features page', function () {
    $company = Company::factory()->create();
    $industry = Industry::factory()->create(['slug' => 'it']);
    $company->industries()->attach($industry->id, ['uses_bookings' => false]);

    Livewire::test(ManageCompanyFeatures::class, ['record' => $company->getRouteKey()])
        ->fillForm([
            'companyIndustries' => [
                ['industry_id' => $industry->id, 'uses_bookings' => true],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($company->industries()->first()->pivot->uses_bookings)->toBeTrue();
});

test('the Edit tab appears in the sub-navigation', function () {
    $company = Company::factory()->create();

    Livewire::test(ManageCompanyFeatures::class, ['record' => $company->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Edit');
});
