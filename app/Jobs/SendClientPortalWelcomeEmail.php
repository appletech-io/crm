<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\EmailProvider;
use App\Enums\EmailTemplateType;
use App\Exceptions\Mail\MicrosoftGraphThrottledException;
use App\Jobs\Concerns\ThrottlesMicrosoftGraphMail;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Services\Mail\Concerns\ReplacesEmailPlaceholders;
use App\Services\Mail\EmailFooter;
use App\Services\Mail\EmailSenderResolver;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\MicrosoftGraphMailer;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendClientPortalWelcomeEmail implements ShouldBeEncrypted, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use ReplacesEmailPlaceholders;
    use ThrottlesMicrosoftGraphMail;

    public int $backoff = 60;

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function graphMailCompany(): ?Company
    {
        return $this->contact->client?->company;
    }

    public function __construct(
        public readonly ClientContact $contact,
        public readonly string $password,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $client = $this->contact->client;

        if (! $client) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('company_id', $client->company_id)
            ->where('industry_id', $client->industry_id)
            ->where('type', EmailTemplateType::ClientPortalWelcome->value)
            ->first();

        if (! $template) {
            return;
        }

        try {
            $mailer = match ($client->company->email_provider) {
                EmailProvider::Mailgun => new MailgunMailer,
                default => new MicrosoftGraphMailer($client->company),
            };

            $replacements = $this->buildReplacements();
            $sender = EmailSenderResolver::resolve($template, $client->consultant);

            $mailer->send(
                to: $this->contact->email,
                subject: $this->replacePlaceholders($template->subject ?? '', $replacements),
                body: $this->replacePlaceholders($template->body ?? '', $replacements).EmailFooter::render($client->company, $sender),
                from: $sender?->email ?? $client->company->defaultFromEmail(),
                attachments: [EmailFooter::logoAttachment($client->company)],
            );

            $client->activities()->create([
                'user_id' => null,
                'type' => ActivityType::Email->value,
                'note' => 'Portal account welcome email sent',
                'body' => "Portal account welcome email sent to {$this->contact->email}",
                'contacted' => true,
            ]);
        } catch (MicrosoftGraphThrottledException $e) {
            $this->release($e->retryAfterSeconds);
        } catch (Throwable $e) {
            Log::error("Failed to send portal welcome email to {$this->contact->email}: {$e->getMessage()}");
            throw $e;
        }
    }

    /** @return array<string, string> */
    private function buildReplacements(): array
    {
        $client = $this->contact->client;
        $contactName = trim(collect([$this->contact->title, $this->contact->first_name, $this->contact->last_name])->filter()->implode(' '));

        return [
            'client_contact_name' => $contactName,
            'client_name' => $client->name ?? '',
            'portal_email' => $this->contact->email ?? '',
            'temporary_password' => $this->password,
            'portal_link' => '<a href="'.url('/client').'">Log In</a>',
        ];
    }
}
