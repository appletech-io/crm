<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\EmailProvider;
use App\Models\EmailTemplate;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\MicrosoftGraphMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendHealthcareApplicationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly HealthcareCandidate $candidate,
        public readonly HealthcareApplication $application,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $industryId = Industry::where('slug', 'healthcare')->value('id');

        $template = EmailTemplate::query()
            ->where('company_id', $this->candidate->company_id)
            ->where('industry_id', $industryId)
            ->where('type', 'application')
            ->first();

        if (! $template) {
            return;
        }

        try {
            $mailer = match ($this->candidate->company->email_provider) {
                EmailProvider::Mailgun => new MailgunMailer,
                default => new MicrosoftGraphMailer($this->candidate->company),
            };

            $mailer->send(
                to: $this->candidate->email,
                subject: $this->replacePlaceholders($template->subject ?? ''),
                body: $this->replacePlaceholders($template->body ?? ''),
                from: $this->candidate->consultant?->email ?? $this->candidate->company->defaultFromEmail(),
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
        $applicationUrl = route('application.healthcare.form', ['token' => $this->application->token]);

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
