<?php

use App\Enums\ReferenceStatus;
use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Models\EducationCandidate;
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
    Cache::put("user.{$this->user->id}.active_industry", 'education');
});

test('edit page renders with tabs', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful();
});

test('personal details can be saved on candidate', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'gender' => 'female',
            'nationality' => 'British',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'London',
            'phone' => '07700900000',
            'mobile' => '07700900001',
            'postcode' => 'SW1A 1AA',
            'city' => 'London',
            'country' => 'United Kingdom',
            'emergency_contact_name' => 'John Doe',
            'emergency_contact_number' => '07700900002',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $candidate->refresh();

    expect($candidate->first_name)->toBe('Jane');
    expect($candidate->last_name)->toBe('Doe');
    expect($candidate->gender)->toBe('female');
    expect($candidate->emergency_contact_name)->toBe('John Doe');
});

test('email must be unique among candidates in the same company', function () {
    EducationCandidate::factory()->create(['company_id' => null, 'email' => 'jane@example.com']);
    $candidate = EducationCandidate::factory()->create(['company_id' => null, 'email' => 'other@example.com']);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm(['email' => 'jane@example.com'])
        ->call('save')
        ->assertHasFormErrors(['email' => 'unique']);
});

test('a candidate keeps its own email as valid when saving unrelated fields', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null, 'email' => 'jane@example.com']);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'email' => 'jane@example.com',
            'first_name' => 'Updated',
            'phone' => '07700900000',
            'mobile' => '07700900001',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

test('references can be viewed and saved via the repeater on the candidate edit form', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldExists('references')
        ->assertSuccessful();

    expect($candidate->references()->count())->toBe(1);
});

test('collapsed reference item label shows a status emoji alongside the text', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'worked_from' => '2019-01-01',
        'status' => 'confirmed',
        'consent_to_contact' => true,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('✅')
        ->assertSee('Jane Smith — Confirmed ✅');
});

test('new references default to pending status and can be moved through the workflow via the repeater', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ])->fresh();

    expect($reference->status)->toBe(ReferenceStatus::Pending);
    expect($reference->last_contacted)->toBeNull();

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set('data.phone', '07700900000')
        ->set('data.mobile', '07700900001')
        ->set("data.references.record-{$reference->id}.status", 'confirmed')
        ->set("data.references.record-{$reference->id}.last_contacted", '2026-06-01')
        ->call('save')
        ->assertHasNoFormErrors();

    $reference->refresh();

    expect($reference->status)->toBe(ReferenceStatus::Confirmed);
    expect($reference->last_contacted->toDateString())->toBe('2026-06-01');
});

test('references default to contact_now being enabled and can be switched off via the repeater', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $reference = $candidate->references()->create([
        'type' => 'character',
        'first_name' => 'Existing',
        'last_name' => 'Referee',
        'worked_from' => '2019-01-01',
        'consent_to_contact' => true,
    ])->fresh();

    expect($reference->contact_now)->toBeTrue();

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->set('data.phone', '07700900000')
        ->set('data.mobile', '07700900001')
        ->set("data.references.record-{$reference->id}.contact_now", false)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($reference->refresh()->contact_now)->toBeFalse();
});

test('employment history can be viewed and saved via the repeater on the candidate edit form', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->employmentHistories()->create([
        'company_name' => 'Oakwood Primary',
        'job_title' => 'Class Teacher',
        'worked_from' => '2020-09-01',
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertFormFieldExists('employmentHistories')
        ->assertSuccessful();

    expect($candidate->employmentHistories()->count())->toBe(1);
});

test('collapsed employment history item label shows the company and year range', function () {
    $candidate = EducationCandidate::factory()->create(['company_id' => null]);

    $candidate->employmentHistories()->create([
        'company_name' => 'Oakwood Primary',
        'job_title' => 'Class Teacher',
        'worked_from' => '2020-09-01',
        'worked_to' => '2022-07-01',
    ]);
    $candidate->employmentHistories()->create([
        'company_name' => 'Elm Secondary',
        'job_title' => 'Head of Year',
        'worked_from' => '2018-09-01',
        'worked_to' => null,
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Oakwood Primary (2020 - 2022)')
        ->assertSee('Elm Secondary (2018 - Present)');
});
