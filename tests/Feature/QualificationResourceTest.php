<?php

use App\Filament\Resources\Qualifications\Pages\EditQualification;
use App\Filament\Resources\Qualifications\Pages\ListQualifications;
use App\Models\Industry;
use App\Models\Qualification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->industry = Industry::factory()->create();
    Cache::put("user.{$this->user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$this->user->id}.active_industry_id", $this->industry->id);
});

test('a non admin cannot view the qualifications list', function () {
    $user = User::factory()->create();
    Cache::put("user.{$user->id}.active_industry", $this->industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $this->industry->id);

    $this->actingAs($user)->get('/crm/qualifications')->assertRedirect('/crm');
});

test('list page renders', function () {
    Livewire::test(ListQualifications::class)
        ->assertSuccessful();
});

test('list only shows qualifications for the active company and industry', function () {
    $ownQualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'PGCE',
    ]);

    $otherQualification = Qualification::factory()->create([
        'name' => 'Other Company Qualification',
    ]);

    Livewire::test(ListQualifications::class)
        ->assertCanSeeTableRecords([$ownQualification])
        ->assertCanNotSeeTableRecords([$otherQualification]);
});

test('can create a qualification', function () {
    Livewire::test(ListQualifications::class)
        ->callAction('create', data: ['name' => 'Early Years'])
        ->assertHasNoActionErrors();

    $qualification = Qualification::where('name', 'Early Years')->first();

    expect($qualification)->not->toBeNull();
    expect($qualification->company_id)->toBe($this->user->company_id);
    expect($qualification->industry_id)->toBe($this->industry->id);
});

test('edit page renders', function () {
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditQualification::class, ['record' => $qualification->getRouteKey()])
        ->assertSuccessful();
});

test('qualification name can be updated', function () {
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
        'name' => 'Old Name',
    ]);

    Livewire::test(EditQualification::class, ['record' => $qualification->getRouteKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($qualification->refresh()->name)->toBe('New Name');
});

test('a qualification can be deleted', function () {
    $qualification = Qualification::factory()->create([
        'company_id' => $this->user->company_id,
        'industry_id' => $this->industry->id,
    ]);

    Livewire::test(EditQualification::class, ['record' => $qualification->getRouteKey()])
        ->callAction('delete');

    expect(Qualification::find($qualification->id))->toBeNull();
});
