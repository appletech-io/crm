<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('guide'));

    $response->assertRedirect(route('login'));
});

test('an authenticated user can view the user guide', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('guide'));

    $response->assertSuccessful();
    $response->assertSeeText('User Guide');
    $response->assertSeeText('Compliance');
});
