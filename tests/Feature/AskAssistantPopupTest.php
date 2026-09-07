<?php

use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

test('the ask assistant popup appears on an admin page when an active industry is set', function () {
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $industry = Industry::factory()->create();
    Cache::put("user.{$user->id}.active_industry", $industry->slug);
    Cache::put("user.{$user->id}.active_industry_id", $industry->id);

    $this->get('/crm')
        ->assertOk()
        ->assertSee('Ask Assistant')
        ->assertSeeHtml('wire:key="ask-assistant-popup"');
});

test('the ask assistant popup does not appear, and does not break the page, with no active industry', function () {
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->get('/crm')
        ->assertOk()
        ->assertDontSeeHtml('wire:key="ask-assistant-popup"');
});
