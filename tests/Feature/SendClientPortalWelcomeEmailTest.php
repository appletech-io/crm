<?php

use App\Enums\ActivityType;
use App\Enums\EmailTemplateType;
use App\Jobs\SendClientPortalWelcomeEmail;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->company = Company::factory()->create([
        'ms_tenant_id' => 'tenant',
        'ms_client_id' => 'client',
        'ms_client_secret' => 'secret',
        'ms_sender_email' => 'sender@example.com',
    ]);
    $this->industry = Industry::factory()->create(['name' => 'Education', 'slug' => 'education']);
    $this->company->industries()->attach($this->industry);

    $this->client = Client::factory()->create([
        'company_id' => $this->company->id,
        'industry_id' => $this->industry->id,
        'name' => 'Ashlawn School',
    ]);

    $this->contact = $this->client->contacts()->create([
        'company_id' => $this->company->id,
        'title' => 'Mrs',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@example.com',
    ]);

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
        'graph.microsoft.com/*' => Http::response([], 202),
    ]);
});

function createClientPortalWelcomeTemplate(Company $company, Industry $industry, ?string $sender = null): void
{
    EmailTemplate::create(array_filter([
        'company_id' => $company->id,
        'industry_id' => $industry->id,
        'name' => 'Client Portal Welcome',
        'type' => EmailTemplateType::ClientPortalWelcome,
        'subject' => 'Your login for {client_name}',
        'body' => 'Dear {client_contact_name}, email: {portal_email}, password: {temporary_password}. {portal_link}',
        'sender' => $sender,
    ]));
}

test('it sends the welcome email with the credentials and logs a client activity', function () {
    createClientPortalWelcomeTemplate($this->company, $this->industry);

    (new SendClientPortalWelcomeEmail($this->contact, 'a-random-password'))->handle();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'graph.microsoft.com')) {
            return false;
        }

        $body = $request['message']['body']['content'];

        return $request['message']['subject'] === 'Your login for Ashlawn School'
            && $request['message']['toRecipients'][0]['emailAddress']['address'] === 'jane.doe@example.com'
            && str_contains($body, 'Dear Mrs Jane Doe')
            && str_contains($body, 'email: jane.doe@example.com')
            && str_contains($body, 'password: a-random-password');
    });

    expect(ClientActivity::where('type', ActivityType::Email)->count())->toBe(1);
});

test('it sends from the clients consultant', function () {
    $consultant = User::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'consultant@example.com',
    ]);

    $this->client->update(['consultant_id' => $consultant->id]);

    createClientPortalWelcomeTemplate($this->company, $this->industry);

    (new SendClientPortalWelcomeEmail($this->contact, 'a-random-password'))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/consultant@example.com/sendMail'));
});

test('it does not send when no client portal welcome template exists', function () {
    (new SendClientPortalWelcomeEmail($this->contact, 'a-random-password'))->handle();

    Http::assertNothingSent();
});
