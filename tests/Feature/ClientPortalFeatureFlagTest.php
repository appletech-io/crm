<?php

use App\Filament\Client\Pages\MyBookings;
use App\Filament\Client\Pages\MyCandidates;
use App\Filament\Client\Pages\RateBookings;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->industry = Industry::factory()->create(['slug' => 'education']);
    $this->company->industries()->attach($this->industry->id, ['uses_bookings' => true]);

    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
    ]);

    $this->contact = ClientContact::factory()->create([
        'company_id' => $this->company->id,
        'client_id' => $this->client->id,
    ]);

    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'client_contact_id' => $this->contact->id,
    ]);
    $this->user->assignRole('client');
    $this->actingAs($this->user);

    // Client-portal access resolves directly from the client's own
    // company/industry, never through the staff active_industry cache —
    // deliberately not setting it here locks that in.
});

test('MyBookings is accessible when the client\'s industry has bookings on', function () {
    expect(MyBookings::canAccess())->toBeTrue();
});

test('MyBookings is hidden when the client\'s industry has bookings off', function () {
    $this->company->industries()->updateExistingPivot($this->industry->id, ['uses_bookings' => false]);

    expect(MyBookings::canAccess())->toBeFalse();
});

test('MyCandidates is hidden when the client\'s industry has bookings off', function () {
    $this->company->industries()->updateExistingPivot($this->industry->id, ['uses_bookings' => false]);

    expect(MyCandidates::canAccess())->toBeFalse();
});

test('RateBookings is hidden when the client\'s industry has bookings off', function () {
    $this->company->industries()->updateExistingPivot($this->industry->id, ['uses_bookings' => false]);

    expect(RateBookings::canAccess())->toBeFalse();
});

test('all three client portal pages are inaccessible to a guest', function () {
    auth()->logout();

    expect(MyBookings::canAccess())->toBeFalse()
        ->and(MyCandidates::canAccess())->toBeFalse()
        ->and(RateBookings::canAccess())->toBeFalse();
});
