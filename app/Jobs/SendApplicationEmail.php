<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\EmailProvider;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Services\MailgunMailer;
use App\Services\MicrosoftGraphMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendApplicationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly EducationCandidate $candidate,
        public readonly EducationApplication $application,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $template = EmailTemplate::query()
            ->where('company_id', $this->candidate->company_id)
            ->where('industry_id', 1)
            ->where('type', 'application')
            ->first();

        if (! $template) {
            return;
        }

        try {
            $mailer = match ($this->candidate->company->email_provider) {
                EmailProvider::Mailgun => new MailgunMailer,
                default => new MicrosoftGraphMailer,
            };

            $mailer->send(
                to: $this->candidate->email,
                subject: $this->replacePlaceholders($template->subject ?? ''),
                body: $this->replacePlaceholders($template->body ?? ''),
                // from: $this->candidate->consultant?->email,
            );

            $this->candidate->activities()->create([
                'user_id' => $this->candidate->consultant_id ?? auth()->id(),
                'type' => ActivityType::Email->value,
                'note' => 'Application pack sent',
                'body' => "Application email sent to {$this->candidate->email}",
                'contacted' => true,
            ]);
        } catch (\Throwable $e) {
            \Log::error("Failed to send application email to {$this->candidate->email}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function replacePlaceholders(string $content): string
    {
        $applicationUrl = route('application.form', ['token' => $this->application->token]);

        $replacements = [
            '{firstname}' => $this->candidate->first_name ?? '',
            '{lastname}' => $this->candidate->last_name ?? '',
            '{email}' => $this->candidate->email ?? '',
            '{application_link}' => $applicationUrl,
            '{expiry_date}' => $this->application->expires_on?->format('d M Y') ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
