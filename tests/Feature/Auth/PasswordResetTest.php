<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('the reset password screen shows the users own company logo, not the generic default', function () {
    Storage::fake('local');
    $contents = file_get_contents(base_path('public/images/appletech.png'));
    Storage::disk('local')->put('company-logos/acme.png', $contents);

    $company = Company::factory()->create(['logo' => 'company-logos/acme.png']);
    $user = User::factory()->create(['company_id' => $company->id]);

    Notification::fake();
    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $company) {
        $response = $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]));

        $response->assertOk();
        $response->assertSee(route('company.logo', $company), escape: false);

        return true;
    });
});

test('the reset password screen falls back to the generic logo for an unrecognised email', function () {
    $response = $this->get(route('password.reset', ['token' => 'some-token', 'email' => 'nobody@example.com']));

    $response->assertOk();
    $response->assertSee(asset('images/appletech.png'), escape: false);
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});
