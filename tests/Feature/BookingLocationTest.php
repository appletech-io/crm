<?php

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
});

test('selecting a client with a default location sets location_id automatically', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);
    ClientLocation::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $client->id, 'name' => 'Not Default']);
    $default = ClientLocation::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $client->id, 'name' => 'Main Site', 'is_default' => true]);

    Livewire::test(CreateBooking::class)
        ->fillForm(['client_id' => $client->id])
        ->assertFormSet(['location_id' => $default->id]);
});

test('selecting a client with no locations leaves location_id empty', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(CreateBooking::class)
        ->fillForm(['client_id' => $client->id])
        ->assertFormSet(['location_id' => null]);
});

test('switching to a second client replaces the default location rather than stacking', function () {
    $clientA = Client::factory()->create(['company_id' => $this->user->company_id]);
    $defaultA = ClientLocation::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $clientA->id, 'is_default' => true]);

    $clientB = Client::factory()->create(['company_id' => $this->user->company_id]);
    $defaultB = ClientLocation::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $clientB->id, 'is_default' => true]);

    $component = Livewire::test(CreateBooking::class)
        ->fillForm(['client_id' => $clientA->id])
        ->assertFormSet(['location_id' => $defaultA->id]);

    $component->fillForm(['client_id' => $clientB->id])
        ->assertFormSet(['location_id' => $defaultB->id]);
});

test('the location select only offers the selected clients own locations', function () {
    $clientA = Client::factory()->create(['company_id' => $this->user->company_id]);
    $locationA = ClientLocation::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $clientA->id, 'name' => 'Client A Site']);

    $clientB = Client::factory()->create(['company_id' => $this->user->company_id]);
    ClientLocation::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $clientB->id, 'name' => 'Client B Site']);

    $options = Livewire::test(CreateBooking::class)
        ->fillForm(['client_id' => $clientA->id])
        ->instance()
        ->form
        ->getComponent('location_id')
        ->getOptions();

    expect($options)->toBe([$locationA->id => 'Client A Site']);
});

test('a booking can be created with a location', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);
    $location = ClientLocation::factory()->create(['company_id' => $this->user->company_id, 'client_id' => $client->id]);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'client_id' => $client->id,
            'candidate_id' => $this->candidate->id,
            'candidate_type' => EducationCandidate::class,
            'job_title_id' => $this->jobTitle->id,
            'location_id' => $location->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
        ])
        ->fillForm([
            'day_periods' => [
                ['date' => '2026-09-01', 'period' => 'full_day'],
            ],
        ])
        ->fillForm([
            'day_rate' => 100,
            'day_charge_rate' => 150,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Booking::first()->location_id)->toBe($location->id);
});
