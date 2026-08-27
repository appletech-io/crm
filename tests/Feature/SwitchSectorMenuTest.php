<?php

use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('the switch sector menu item shows the active sector name in brackets', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $industry = Industry::factory()->create(['slug' => 'education', 'name' => 'Education']);
    $user->industries()->attach($industry);
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    $this->actingAs($user)
        ->get('/crm')
        ->assertSuccessful()
        ->assertSee('Switch Sector (Education)');
});

test('the switch sector menu item has no bracketed name when no sector is active', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get('/crm')
        ->assertSuccessful()
        ->assertSee('Switch Sector')
        ->assertDontSee('Switch Sector (');
});
