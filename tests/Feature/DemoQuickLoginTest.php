<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * Overrides the container's 'env' binding for the current test only — the
 * same value Illuminate\Foundation\Application::environment() itself reads.
 *
 * PreventRequestForgery (CSRF) is normally skipped in tests via its own
 * $this->app->runningUnitTests() check, which reads this same binding —
 * so overriding it to anything other than "testing" re-enables CSRF for
 * the rest of the test too. Disabling it explicitly here keeps that an
 * implementation detail rather than something every test below has to
 * account for.
 */
function actingInEnvironment(string $environment): void
{
    app()->instance('env', $environment);
    test()->withoutMiddleware(PreventRequestForgery::class);
}

test('the quick-login route is unreachable outside the demo environment', function () {
    actingInEnvironment('testing');

    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => $company->id]);
    $admin->assignRole('admin');

    $this->post('/demo-quick-login', ['user_id' => $admin->id])
        ->assertForbidden();

    expect(auth()->check())->toBeFalse();
});

test('quick-login logs in a staff user and redirects to the CRM in the demo environment', function () {
    actingInEnvironment('demo');

    $company = Company::factory()->create();
    $consultant = User::factory()->create(['company_id' => $company->id]);
    $consultant->assignRole('consultant');

    $this->post('/demo-quick-login', ['user_id' => $consultant->id])
        ->assertRedirect('/crm');

    expect(auth()->id())->toBe($consultant->id);
});

test('quick-logging in as a second user replaces the first, even without a prior real login', function () {
    actingInEnvironment('demo');

    $companyA = Company::factory()->create();
    $firstUser = User::factory()->create(['company_id' => $companyA->id]);
    $firstUser->assignRole('consultant');

    $companyB = Company::factory()->create();
    $secondUser = User::factory()->create(['company_id' => $companyB->id]);
    $secondUser->assignRole('consultant');

    $this->post('/demo-quick-login', ['user_id' => $firstUser->id]);
    $this->post('/demo-quick-login', ['user_id' => $secondUser->id]);

    expect(auth()->id())->toBe($secondUser->id);
});

test('quick-login redirects a candidate portal account to the candidate portal', function () {
    actingInEnvironment('demo');

    $candidateUser = User::factory()->create();
    $candidateUser->assignRole('candidate');

    $this->post('/demo-quick-login', ['user_id' => $candidateUser->id])
        ->assertRedirect('/candidate');

    expect(auth()->id())->toBe($candidateUser->id);
});

test('quick-login redirects a client portal account to the client portal', function () {
    actingInEnvironment('demo');

    $clientUser = User::factory()->create();
    $clientUser->assignRole('client');

    $this->post('/demo-quick-login', ['user_id' => $clientUser->id])
        ->assertRedirect('/client');

    expect(auth()->id())->toBe($clientUser->id);
});

test('quick-login works for a user in a different company than any currently authenticated one', function () {
    actingInEnvironment('demo');

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $adminA = User::factory()->create(['company_id' => $companyA->id]);
    $adminA->assignRole('admin');
    $this->actingAs($adminA);

    $adminB = User::factory()->create(['company_id' => $companyB->id]);
    $adminB->assignRole('admin');

    $this->post('/demo-quick-login', ['user_id' => $adminB->id])
        ->assertRedirect('/crm');

    expect(auth()->id())->toBe($adminB->id);
});

test('quick-login 404s for a non-existent user id', function () {
    actingInEnvironment('demo');

    $this->post('/demo-quick-login', ['user_id' => 999999])
        ->assertNotFound();
});

test('the login page shows the quick-login picker only in the demo environment', function () {
    actingInEnvironment('demo');

    $company = Company::factory()->create(['name' => 'Acme Recruitment']);
    $admin = User::factory()->create(['company_id' => $company->id, 'name' => 'Dana Admin']);
    $admin->assignRole('admin');

    $siteAdmin = User::factory()->create(['name' => 'Sam Site Admin']);
    $siteAdmin->assignRole('site_admin');

    $this->get('/login')
        ->assertSee('Demo quick login')
        ->assertSee('Acme Recruitment')
        ->assertSee('Log in as Site Admin');
});

test('the login page does not show the quick-login picker outside the demo environment', function () {
    actingInEnvironment('testing');

    Company::factory()->create(['name' => 'Acme Recruitment']);

    $this->get('/login')
        ->assertDontSee('Demo quick login')
        ->assertDontSee('Acme Recruitment');
});
