<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Models\MarketingCampaign;
use App\Services\Mail\Concerns\ReplacesEmailPlaceholders;
use App\Services\Mail\CustomTemplatePlaceholders;
use App\Services\Mail\EmailFooter;
use App\Services\Mail\EmailSenderResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCustomTemplateEmail implements ShouldQueue
{
    use Queueable;
    use ReplacesEmailPlaceholders;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * $template is null for an ad-hoc email, in which case $adHocSubject and
     * $adHocBody carry the raw (pre-placeholder-substitution) content instead.
     */
    public function __construct(
        public readonly ?EmailTemplate $template,
        public readonly EducationCandidate|HealthcareCandidate|Client $recipient,
        public readonly ?int $sentByUserId = null,
        public readonly ?string $adHocSubject = null,
        public readonly ?string $adHocBody = null,
        public readonly ?MarketingCampaign $campaign = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $contact = $this->recipient instanceof Client ? $this->recipient->bookingContact() : null;
        $email = $this->recipient instanceof Client ? $contact?->email : $this->recipient->email;

        if (blank($email)) {
            return;
        }

        $company = $this->recipient->company;
        $consultant = $this->recipient->consultant;
        $sender = EmailSenderResolver::resolve($this->template, $consultant);
        $replacements = CustomTemplatePlaceholders::resolve($this->recipient, $contact);

        try {
            $mailer = $company->mailer();

            $subject = $this->replacePlaceholders($this->template?->subject ?? $this->adHocSubject ?? '', $replacements);
            $body = $this->replacePlaceholders($this->template?->body ?? $this->adHocBody ?? '', $replacements);

            $mailer->send(
                to: $email,
                subject: $subject,
                body: $body.EmailFooter::render($company, $sender),
                from: $sender?->email ?? $company->defaultFromEmail(),
                attachments: [EmailFooter::logoAttachment()],
            );

            $this->recipient->activities()->create([
                'user_id' => $this->sentByUserId,
                'type' => ActivityType::Email->value,
                'note' => 'Email sent: '.($this->template?->name ?? $subject),
                'body' => "Email sent to {$email}: {$subject}",
                'contacted' => true,
            ]);

            if ($this->campaign && $this->recipient instanceof Client) {
                $this->campaign->sends()->create([
                    'client_id' => $this->recipient->id,
                    'client_contact_id' => $contact?->id,
                    'email_template_id' => $this->template?->id,
                    'sent_by' => $this->sentByUserId,
                    'subject' => $subject,
                    'body' => $body,
                ]);
            }
        } catch (Throwable $e) {
            Log::error("Failed to send custom template email to {$email}: {$e->getMessage()}");
            throw $e;
        }
    }
}
