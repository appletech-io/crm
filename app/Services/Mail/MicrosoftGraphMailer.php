<?php

namespace App\Services\Mail;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MicrosoftGraphMailer
{
    public function __construct(private readonly Company $company) {}

    /** @param  array<int, array{name: string, path: string, mimeType?: string}>  $attachments */
    public function send(string $to, string $subject, string $body, ?string $from = null, array $attachments = []): void
    {
        $this->guardConfiguration();

        $sender = $from ?? $this->company->ms_sender_email;

        $message = [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $body,
            ],
            'toRecipients' => [
                ['emailAddress' => ['address' => $to]],
            ],
        ];

        if (filled($attachments)) {
            $message['attachments'] = collect($attachments)
                ->map(fn (array $attachment): array => [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $attachment['name'],
                    'contentType' => $attachment['mimeType'] ?? 'application/octet-stream',
                    'contentBytes' => base64_encode(file_get_contents($attachment['path'])),
                ])
                ->all();
        }

        Http::withToken($this->accessToken())
            ->post("https://graph.microsoft.com/v1.0/users/{$sender}/sendMail", [
                'message' => $message,
                'saveToSentItems' => true,
            ])
            ->throwUnlessStatus(202);
    }

    private function accessToken(): string
    {
        return Cache::remember("ms_graph_token:{$this->company->id}", now()->addMinutes(55), function () {
            return Http::asForm()
                ->post("https://login.microsoftonline.com/{$this->company->ms_tenant_id}/oauth2/v2.0/token", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->company->ms_client_id,
                    'client_secret' => $this->company->ms_client_secret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ])
                ->throw()
                ->json('access_token');
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
            throw new RuntimeException('Microsoft Graph is not configured for this company. Check the Email Settings on the company record.');
        }
    }
}
