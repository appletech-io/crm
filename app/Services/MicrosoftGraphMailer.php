<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MicrosoftGraphMailer
{
    public function __construct(private readonly Company $company) {}

    public function send(string $to, string $subject, string $body): void
    {
        $this->guardConfiguration();

        Http::withToken($this->accessToken())
            ->post("https://graph.microsoft.com/v1.0/users/{$this->company->ms_sender_email}/sendMail", [
                'message' => [
                    'subject' => $subject,
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => $body,
                    ],
                    'toRecipients' => [
                        ['emailAddress' => ['address' => $to]],
                    ],
                ],
                'saveToSentItems' => true,
            ])
            ->throwUnlessStatus(202);
    }

    private function accessToken(): string
    {
        $cacheKey = "ms_graph_token_company_{$this->company->id}";

        return Cache::remember($cacheKey, now()->addMinutes(55), function () {
            $response = Http::asForm()
                ->post("https://login.microsoftonline.com/{$this->company->ms_tenant_id}/oauth2/v2.0/token", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->company->ms_client_id,
                    'client_secret' => $this->company->ms_client_secret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ])
                ->throw();

            return $response->json('access_token');
        });
    }

    private function guardConfiguration(): void
    {
        if (
            blank($this->company->ms_tenant_id) ||
            blank($this->company->ms_client_id) ||
            blank($this->company->ms_client_secret) ||
            blank($this->company->ms_sender_email)
        ) {
            throw new RuntimeException("Microsoft Graph is not configured for company [{$this->company->id}].");
        }
    }
}
