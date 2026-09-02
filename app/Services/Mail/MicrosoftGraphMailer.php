<?php

namespace App\Services\Mail;

use App\Exceptions\Mail\MicrosoftGraphThrottledException;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MicrosoftGraphMailer
{
    /**
     * Used when Graph throttles a request but doesn't send a Retry-After
     * header of its own — a conservative guess, not a documented value.
     */
    private const DEFAULT_THROTTLE_RETRY_SECONDS = 60;

    public function __construct(private readonly Company $company) {}

    /** @param  array<int, array{name: string, path?: string, content?: string, mimeType?: string, inline?: bool, contentId?: string}>  $attachments */
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
                ->map(fn (array $attachment): array => array_filter([
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $attachment['name'],
                    'contentType' => $attachment['mimeType'] ?? 'application/octet-stream',
                    'contentBytes' => base64_encode($attachment['content'] ?? file_get_contents($attachment['path'])),
                    'isInline' => $attachment['inline'] ?? false,
                    'contentId' => $attachment['contentId'] ?? null,
                ], fn ($value): bool => $value !== null))
                ->all();
        }

        $response = Http::withToken($this->accessToken())
            ->post("https://graph.microsoft.com/v1.0/users/{$sender}/sendMail", [
                'message' => $message,
                'saveToSentItems' => true,
            ]);

        if ($response->status() === 429) {
            throw new MicrosoftGraphThrottledException(
                (int) ($response->header('Retry-After') ?: self::DEFAULT_THROTTLE_RETRY_SECONDS)
            );
        }

        $response->throwUnlessStatus(202);
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
