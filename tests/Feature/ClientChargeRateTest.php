<?php

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\CompanyIndustry;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
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
    $this->user->company->industries()->attach($this->industry->id);
    Cache::put("user.{$this->user->id}.active_industry", 'education');
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);

    $this->jobTitle = JobTitle::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);
});

test('a charge rate can be added per job title via the Charge Rates tab', function () {
    $client = Client::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => Cache::get("user.{$this->user->id}.active_industry_id"),
    ]);

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->fillForm([
            'chargeRates' => [
                'item-1' => [
                    'job_title_id' => $this->jobTitle->id,
                    'day_rate' => '120.00',
                    'half_day_rate' => '60.00',
                    'hourly_rate' => '15.00',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $chargeRate = PayRate::where('model_id', $client->id)
        ->where('model_type', Client::class)
        ->first();

    expect($chargeRate)->not->toBeNull()
        ->and($chargeRate->job_title_id)->toBe($this->jobTitle->id)
        ->and($chargeRate->day_rate)->toEqual(120.0)
        ->and($chargeRate->half_day_rate)->toEqual(60.0)
        ->and($chargeRate->hourly_rate)->toEqual(15.0);
});

test('a candidate pay rate and a client charge rate for the same job title stay independent', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    PayRate::create([
        'company_id' => $this->user->company_id,
        'model_type' => Client::class,
        'model_id' => $client->id,
        'job_title_id' => $this->jobTitle->id,
        'day_rate' => 120,
        'half_day_rate' => 60,
        'hourly_rate' => 15,
    ]);

    expect(PayRate::where('model_type', Client::class)->count())->toBe(1)
        ->and(PayRate::where('model_type', EducationCandidate::class)->count())->toBe(0);
});

test('Sleep-In and Waking Night charge rate fields are hidden on the Charge Rates tab when complex_booking is off', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertDontSee('Sleep-In Charge Rate')
        ->assertDontSee('Waking Night Charge Rate');
});

test('Sleep-In and Waking Night charge rates can be set per job title once complex_booking is on', function () {
    CompanyIndustry::where('company_id', $this->user->company_id)
        ->where('industry_id', $this->industry->id)
        ->update(['complex_booking' => true]);

    $client = Client::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
        ->assertSee('Sleep-In Charge Rate')
        ->assertSee('Waking Night Charge Rate')
        ->fillForm([
            'chargeRates' => [
                'item-1' => [
                    'job_title_id' => $this->jobTitle->id,
                    'hourly_rate' => '15.00',
                    'sleep_in_rate' => '65.00',
                    'waking_night_rate' => '22.00',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $chargeRate = PayRate::where('model_id', $client->id)
        ->where('model_type', Client::class)
        ->first();

    expect($chargeRate->sleep_in_rate)->toEqual(65.0)
        ->and($chargeRate->waking_night_rate)->toEqual(22.0);
});
