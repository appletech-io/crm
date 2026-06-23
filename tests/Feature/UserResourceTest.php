<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('non-admin cannot access users resource', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ListUsers::class)->assertForbidden();
});

test('admin can access users resource', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)->assertSuccessful();
});

test('site_admin can access users resource', function () {
    $siteAdmin = User::factory()->create();
    $siteAdmin->assignRole('site_admin');
    $this->actingAs($siteAdmin);

    Livewire::test(ListUsers::class)->assertSuccessful();
});

test('admin can create a user with a role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New Consultant',
            'email' => 'consultant@example.com',
            'password' => 'password',
            'roles' => [Role::where('name', 'consultant')->first()->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('email', 'consultant@example.com')->first();
    expect($created)->not->toBeNull();
    expect($created->hasRole('consultant'))->toBeTrue();
});

test('admin can edit a user role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $user = User::factory()->create(['company_id' => $admin->company_id]);
    $user->assignRole('resourcer');

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'roles' => [Role::where('name', 'consultant')->first()->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->hasRole('consultant'))->toBeTrue();
});
