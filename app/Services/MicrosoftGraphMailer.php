<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MicrosoftGraphMailer
{
    public function send(string $to, string $subject, string $body, ?string $from = null): void
    {
        $this->guardConfiguration();

        $sender = $from ?? config('services.microsoft.sender_email');

        Http::withToken($this->accessToken())
            ->post("https://graph.microsoft.com/v1.0/users/{$sender}/sendMail", [
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
        return Cache::remember('ms_graph_token', now()->addMinutes(55), function () {
            return Http::asForm()
                ->post('https://login.microsoftonline.com/'.config('services.microsoft.tenant_id').'/oauth2/v2.0/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('services.microsoft.client_id'),
                    'client_secret' => config('services.microsoft.client_secret'),
                    'scope' => 'https://graph.microsoft.com/.default',
                ])
                ->throw()
                ->json('access_token');
        });
    }

    private function guardConfiguration(): void
    {
        if (
            blank(config('services.microsoft.tenant_id')) ||
            blank(config('services.microsoft.client_id')) ||
            blank(config('services.microsoft.client_secret')) ||
            blank(config('services.microsoft.sender_email'))
        ) {
            throw new RuntimeException('Microsoft Graph is not configured (check MS_TENANT_ID, MS_CLIENT_ID, MS_CLIENT_SECRET, MS_SENDER_EMAIL in .env).');
        }
    }
}
