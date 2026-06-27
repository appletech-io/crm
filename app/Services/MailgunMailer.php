<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MailgunMailer
{
    public function send(string $to, string $subject, string $body, ?string $from = null): void
    {
        $domain = config('services.mailgun.domain');
        $apiKey = config('services.mailgun.secret');
        $fromAddress = $from ?? config('mail.from.address');
        $fromName = config('mail.from.name');
        $endpoint = config('services.mailgun.endpoint', 'api.mailgun.net');

        if (blank($domain) || blank($apiKey) || blank($fromAddress)) {
            throw new RuntimeException('Mailgun is not configured (check MAILGUN_DOMAIN, MAILGUN_SECRET, MAIL_FROM_ADDRESS in .env).');
        }

        Http::withBasicAuth('api', $apiKey)
            ->asForm()
            ->post("https://{$endpoint}/v3/{$domain}/messages", [
                'from' => "{$fromName} <{$fromAddress}>",
                'to' => $to,
                'subject' => $subject,
                'html' => $body,
                'text' => strip_tags($body),
            ])
            ->throw();
    }
}
