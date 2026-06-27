<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MailgunMailer
{
    public function send(string $to, string $subject, string $body): void
    {
        $domain = config('services.mailgun.domain');
        $apiKey = config('services.mailgun.secret');
        $from = config('mail.from.address');

        if (blank($domain) || blank($apiKey) || blank($from)) {
            throw new RuntimeException('Mailgun is not configured (check MAILGUN_DOMAIN, MAILGUN_SECRET, MAIL_FROM_ADDRESS in .env).');
        }

        Http::withBasicAuth('api', $apiKey)
            ->asForm()
            ->post("https://api.mailgun.net/v3/{$domain}/messages", [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'html' => $body,
            ])
            ->throw();
    }
}
