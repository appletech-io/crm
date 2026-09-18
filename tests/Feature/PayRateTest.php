<?php

use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Models\CompanyIndustry;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
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

test('pay rate stores pounds as pence and casts back to pounds', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $payRate = PayRate::create([
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 12.50,
        'day_rate' => 100,
        'half_day_rate' => 50,
    ]);

    expect($payRate->getRawOriginal('hourly_rate'))->toBe(1250)
        ->and($payRate->getRawOriginal('day_rate'))->toBe(10000)
        ->and($payRate->getRawOriginal('half_day_rate'))->toBe(5000)
        ->and($payRate->fresh()->hourly_rate)->toEqual(12.5)
        ->and($payRate->fresh()->day_rate)->toEqual(100.0)
        ->and($payRate->fresh()->half_day_rate)->toEqual(50.0);
});

test('a pay rate can be added per job title via the Pay Rates tab', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'phone' => '07700900000',
            'mobile' => '07700900001',
            'payRates' => [
                'item-1' => [
                    'job_title_id' => $this->jobTitle->id,
                    'hourly_rate' => '12.50',
                    'day_rate' => '100.00',
                    'half_day_rate' => '50.00',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $payRate = PayRate::where('model_id', $candidate->id)
        ->where('model_type', EducationCandidate::class)
        ->first();

    expect($payRate)->not->toBeNull()
        ->and($payRate->job_title_id)->toBe($this->jobTitle->id)
        ->and($payRate->hourly_rate)->toEqual(12.5)
        ->and($payRate->day_rate)->toEqual(100.0)
        ->and($payRate->half_day_rate)->toEqual(50.0);
});

test('an invalid monetary amount fails validation', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'phone' => '07700900000',
            'mobile' => '07700900001',
            'payRates' => [
                'item-1' => [
                    'job_title_id' => $this->jobTitle->id,
                    'hourly_rate' => '12.999',
                ],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect(PayRate::where('model_id', $candidate->id)->exists())->toBeFalse();
});

test('the same job title cannot be added twice for a candidate', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'phone' => '07700900000',
            'mobile' => '07700900001',
            'payRates' => [
                'item-1' => ['job_title_id' => $this->jobTitle->id, 'hourly_rate' => '10.00'],
                'item-2' => ['job_title_id' => $this->jobTitle->id, 'hourly_rate' => '20.00'],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors();

    expect(PayRate::where('model_id', $candidate->id)->count())->toBe(0);
});

test('Sleep-In and Waking Night rate fields are hidden on the Pay Rates tab when complex_booking is off', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('Sleep-In Rate')
        ->assertDontSee('Waking Night Rate');
});

test('Sleep-In and Waking Night rates can be set per job title once complex_booking is on', function () {
    CompanyIndustry::where('company_id', $this->user->company_id)
        ->where('industry_id', $this->industry->id)
        ->update(['complex_booking' => true]);

    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Sleep-In Rate')
        ->assertSee('Waking Night Rate')
        ->fillForm([
            'phone' => '07700900000',
            'mobile' => '07700900001',
            'payRates' => [
                'item-1' => [
                    'job_title_id' => $this->jobTitle->id,
                    'hourly_rate' => '12.50',
                    'sleep_in_rate' => '45.00',
                    'waking_night_rate' => '18.00',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $payRate = PayRate::where('model_id', $candidate->id)
        ->where('model_type', EducationCandidate::class)
        ->first();

    expect($payRate->sleep_in_rate)->toEqual(45.0)
        ->and($payRate->waking_night_rate)->toEqual(18.0);
});

test('a Sleep-In/Waking Night rate entered while complex_booking was on survives an unrelated edit after it is switched off', function () {
    CompanyIndustry::where('company_id', $this->user->company_id)
        ->where('industry_id', $this->industry->id)
        ->update(['complex_booking' => true]);

    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    PayRate::create([
        'company_id' => $this->user->company_id,
        'model_type' => EducationCandidate::class,
        'model_id' => $candidate->id,
        'job_title_id' => $this->jobTitle->id,
        'hourly_rate' => 12.50,
        'sleep_in_rate' => 45.00,
        'waking_night_rate' => 18.00,
    ]);

    CompanyIndustry::where('company_id', $this->user->company_id)
        ->where('industry_id', $this->industry->id)
        ->update(['complex_booking' => false]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['phone' => '07700900002', 'mobile' => '07700900003'])
        ->call('save')
        ->assertHasNoFormErrors();

    $payRate = PayRate::where('model_id', $candidate->id)
        ->where('model_type', EducationCandidate::class)
        ->first();

    expect($payRate->sleep_in_rate)->toEqual(45.0)
        ->and($payRate->waking_night_rate)->toEqual(18.0);
});
