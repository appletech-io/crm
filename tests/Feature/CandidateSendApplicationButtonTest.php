<?php

use App\Filament\Resources\EducationCandidates\Pages\EditEducationCandidate;
use App\Filament\Resources\HealthcareCandidates\Pages\EditHealthcareCandidate;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->user->company->update([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);
    $this->actingAs($this->user);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);
});

function actingAsIndustryUserFor(string $slug): Industry
{
    $industry = Industry::factory()->create(['slug' => $slug]);
    Cache::put('user.'.test()->user->id.'.active_industry', $industry->slug);
    Cache::put('user.'.test()->user->id.'.active_industry_id', $industry->id);

    return $industry;
}

function makeApplicationTemplate(Industry $industry): void
{
    EmailTemplate::create([
        'company_id' => test()->user->company_id,
        'industry_id' => $industry->id,
        'name' => 'Application',
        'type' => 'application',
        'subject' => 'Apply now, {firstname}',
        'body' => 'Hi {firstname}, please apply: {application_link}',
    ]);
}

test('a send application button shows on an education candidate with no application', function () {
    actingAsIndustryUserFor('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Send Application')
        ->assertDontSee('Application Pending')
        ->assertDontSee('Application Complete');
});

test('clicking send application on an education candidate creates an application and sends the email immediately', function () {
    $industry = actingAsIndustryUserFor('education');
    makeApplicationTemplate($industry);
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->call('sendApplicationEmail')
        ->assertSee('Application Pending')
        ->assertDontSee('Send Application');

    expect($candidate->application()->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMail'));
});

test('the send application button does not show once an application already exists', function () {
    actingAsIndustryUserFor('education');
    $candidate = EducationCandidate::factory()->create(['company_id' => $this->user->company_id]);
    $candidate->application()->create([
        'email' => $candidate->email,
        'status' => 'pending',
        'token' => Str::uuid(),
        'expires_on' => now()->addDays(7)->toDateString(),
    ]);

    Livewire::test(EditEducationCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertDontSee('Send Application')
        ->assertSee('Application Pending');
});

test('a send application button shows on a healthcare candidate with no application', function () {
    actingAsIndustryUserFor('healthcare');
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSee('Send Application')
        ->assertDontSee('Application Pending')
        ->assertDontSee('Application Complete');
});

test('clicking send application on a healthcare candidate creates an application and sends the email immediately', function () {
    $industry = actingAsIndustryUserFor('healthcare');
    makeApplicationTemplate($industry);
    $candidate = HealthcareCandidate::factory()->create(['company_id' => $this->user->company_id]);

    Livewire::test(EditHealthcareCandidate::class, ['record' => $candidate->getRouteKey()])
        ->call('sendApplicationEmail')
        ->assertSee('Application Pending')
        ->assertDontSee('Send Application');

    expect($candidate->application()->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMail'));
});
