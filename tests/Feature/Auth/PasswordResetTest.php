<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

test('the reset link is sent from the users agency, through that agencys own mailer', function () {
    Notification::fake();
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $company = Company::factory()->create([
        'name' => 'Acme Recruitment',
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'info@acme-recruitment.test',
    ]);
    $user = User::factory()->create(['company_id' => $company->id, 'email' => 'consultant@example.com']);

    $this->post(route('password.request'), ['email' => $user->email]);

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), 'graph.microsoft.com')) {
            return false;
        }

        return str_contains($request->url(), '/users/info@acme-recruitment.test/sendMail')
            && $request['message']['subject'] === 'Reset your Acme Recruitment password'
            && $request['message']['toRecipients'][0]['emailAddress']['address'] === $user->email
            && str_contains($request['message']['body']['content'], url('/reset-password/'))
            && str_contains($request['message']['body']['content'], 'Acme Recruitment');
    });

    Notification::assertNothingSent();
});

test('a reset link sent by the agency still resets the password', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);

    $company = Company::factory()->create([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'info@acme-recruitment.test',
    ]);
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->post(route('password.request'), ['email' => $user->email]);

    $token = null;

    Http::assertSent(function ($request) use (&$token) {
        if (! str_contains($request->url(), 'graph.microsoft.com')) {
            return false;
        }

        preg_match('#/reset-password/([^"?]+)#', $request['message']['body']['content'], $matches);
        $token = $matches[1] ?? null;

        return true;
    });

    expect($token)->not->toBeNull();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login', absolute: false));

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('it falls back to the platform notification when the users company has no sending address', function () {
    Notification::fake();
    Http::fake();

    $company = Company::factory()->create(['ms_sender_email' => null]);
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
    Http::assertNothingSent();
});

test('no reset email is sent for an email address that has no user', function () {
    Notification::fake();
    Http::fake();

    $this->post(route('password.request'), ['email' => 'nobody@example.com']);

    Notification::assertNothingSent();
    Http::assertNothingSent();
});
