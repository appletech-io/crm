<?php

use App\Filament\Resources\EducationCandidates\Pages\ListEducationCandidates;
use App\Filament\Resources\HealthcareCandidates\Pages\ListHealthcareCandidates;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
});

function actingAsConsultantFor(Company $company, Industry $industry): User
{
    $consultant = User::factory()->create(['company_id' => $company->id]);
    $consultant->assignRole('consultant');
    test()->actingAs($consultant);

    Cache::put("user.{$consultant->id}.active_industry", $industry->slug);
    Cache::put("user.{$consultant->id}.active_industry_id", $industry->id);

    return $consultant;
}

test('the Search tab shows on the Education candidates list when bookings is on', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($industry->id, ['uses_bookings' => true]);
    actingAsConsultantFor($this->company, $industry);

    Livewire::test(ListEducationCandidates::class)
        ->assertSeeHtml('$set(\'activeSection\', \'search\')')
        ->assertSet('activeSection', 'search');
});

test('the Search tab is hidden and activeSection forced to all on the Education candidates list when bookings is off', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($industry->id, ['uses_bookings' => false]);
    actingAsConsultantFor($this->company, $industry);

    Livewire::test(ListEducationCandidates::class)
        ->assertDontSeeHtml('$set(\'activeSection\', \'search\')')
        ->assertSet('activeSection', 'all');
});

test('the Search tab shows on the Healthcare candidates list when bookings is on', function () {
    $industry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company->industries()->attach($industry->id, ['uses_bookings' => true]);
    actingAsConsultantFor($this->company, $industry);

    Livewire::test(ListHealthcareCandidates::class)
        ->assertSeeHtml('$set(\'activeSection\', \'search\')')
        ->assertSet('activeSection', 'search');
});

test('the Search tab is hidden and activeSection forced to all on the Healthcare candidates list when bookings is off', function () {
    $industry = Industry::factory()->create(['slug' => 'healthcare']);
    $this->company->industries()->attach($industry->id, ['uses_bookings' => false]);
    actingAsConsultantFor($this->company, $industry);

    Livewire::test(ListHealthcareCandidates::class)
        ->assertDontSeeHtml('$set(\'activeSection\', \'search\')')
        ->assertSet('activeSection', 'all');
});
