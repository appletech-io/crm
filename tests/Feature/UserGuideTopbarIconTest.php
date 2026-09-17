<?php

use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

test('the user guide icon appears next to the search bar on an admin page', function () {
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $industry = Industry::factory()->create();
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    $this->get('/crm')
        ->assertOk()
        ->assertSeeHtml(route('guide'));
});
