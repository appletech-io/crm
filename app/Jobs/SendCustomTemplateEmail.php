<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Services\Mail\Concerns\ReplacesEmailPlaceholders;
use App\Services\Mail\CustomTemplatePlaceholders;
use App\Services\Mail\EmailFooter;
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

    public function __construct(
        public readonly EmailTemplate $template,
        public readonly EducationCandidate|HealthcareCandidate|Client $recipient,
        public readonly ?int $sentByUserId = null,
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
        $replacements = CustomTemplatePlaceholders::resolve($this->recipient, $contact);

        try {
            $mailer = $company->mailer();

            $subject = $this->replacePlaceholders($this->template->subject ?? '', $replacements);
            $body = $this->replacePlaceholders($this->template->body ?? '', $replacements);

            $mailer->send(
                to: $email,
                subject: $subject,
                body: $body.EmailFooter::render($company, $consultant),
                from: $consultant?->email ?? $company->defaultFromEmail(),
                attachments: [EmailFooter::logoAttachment()],
            );

            $this->recipient->activities()->create([
                'user_id' => $this->sentByUserId,
                'type' => ActivityType::Email->value,
                'note' => "Email sent: {$this->template->name}",
                'body' => "Email sent to {$email}: {$subject}",
                'contacted' => true,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to send custom template email to {$email}: {$e->getMessage()}");
            throw $e;
        }
    }
}
