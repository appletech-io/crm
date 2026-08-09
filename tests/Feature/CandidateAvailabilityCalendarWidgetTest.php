<?php

use App\Enums\CandidateAvailabilityStatus;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Widgets\CandidateAvailabilityCalendar;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $industry = Industry::factory()->create(['slug' => 'education']);
    Cache::put("user.{$this->user->id}.active_industry", $industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $industry->id);

    $this->candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
});

test('it renders for a candidate record', function () {
    Livewire::test(CandidateAvailabilityCalendar::class, ['record' => $this->candidate])
        ->assertSuccessful();
});

test('a consultant can set a days availability', function () {
    Livewire::test(CandidateAvailabilityCalendar::class, ['record' => $this->candidate])
        ->call('setAvailabilityStatus', '2026-08-10', CandidateAvailabilityStatus::AvailableAm->value);

    $availability = $this->candidate->availabilities()->whereDate('date', '2026-08-10')->first();

    expect($availability)->not->toBeNull();
    expect($availability->status)->toBe(CandidateAvailabilityStatus::AvailableAm);
});

test('a consultant can change an already set days availability', function () {
    $this->candidate->availabilities()->create([
        'date' => '2026-08-10',
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    Livewire::test(CandidateAvailabilityCalendar::class, ['record' => $this->candidate])
        ->call('setAvailabilityStatus', '2026-08-10', CandidateAvailabilityStatus::NotAvailable->value);

    expect($this->candidate->availabilities()->count())->toBe(1);
    expect($this->candidate->availabilities()->first()->status)->toBe(CandidateAvailabilityStatus::NotAvailable);
});

test('clearing a days availability removes the stored record instead of saving a null status', function () {
    $this->candidate->availabilities()->create([
        'date' => '2026-08-10',
        'status' => CandidateAvailabilityStatus::Available->value,
    ]);

    Livewire::test(CandidateAvailabilityCalendar::class, ['record' => $this->candidate])
        ->call('setAvailabilityStatus', '2026-08-10', null);

    expect($this->candidate->availabilities()->count())->toBe(0);
});

test('a booked day cannot be overridden even if submitted directly', function () {
    $client = Client::factory()->create(['company_id' => $this->user->company_id]);
    $booking = Booking::factory()->create([
        'company_id' => $this->user->company_id,
        'client_id' => $client->id,
        'candidate_id' => $this->candidate->id,
        'candidate_type' => EducationCandidate::class,
    ]);
    $booking->dayPeriods()->create([
        'company_id' => $this->user->company_id,
        'date' => '2026-08-10',
        'period' => 'full_day',
    ]);

    Livewire::test(CandidateAvailabilityCalendar::class, ['record' => $this->candidate])
        ->call('setAvailabilityStatus', '2026-08-10', CandidateAvailabilityStatus::Available->value);

    expect($this->candidate->availabilities()->whereDate('date', '2026-08-10')->exists())->toBeFalse();
});

test('week navigation moves the displayed week forward and back', function () {
    $component = Livewire::test(CandidateAvailabilityCalendar::class, ['record' => $this->candidate]);

    $initialWeekStart = $component->get('weekStart');

    $component->call('goToNextWeek');
    expect($component->get('weekStart'))->toBe(Carbon::parse($initialWeekStart)->addWeek()->toDateString());

    $component->call('goToPreviousWeek');
    expect($component->get('weekStart'))->toBe($initialWeekStart);
});

test('the booked status is never offered as a selectable option', function () {
    expect(CandidateAvailabilityStatus::settableOptions())->not->toHaveKey(CandidateAvailabilityStatus::Booked->value);
    expect(CandidateAvailabilityStatus::options())->toHaveKey(CandidateAvailabilityStatus::Booked->value);
});

test('the edit page renders the weekly availability tab', function () {
    Livewire::test(EditEducationCandidate::class, ['record' => $this->candidate->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Weekly Availability');
});
