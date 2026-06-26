<?php

namespace App\Jobs;

use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Services\MicrosoftGraphMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendApplicationEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly EducationCandidate $candidate,
        public readonly EducationApplication $application,
    ) {}

    public function handle(): void
    {
        $template = EmailTemplate::query()
            ->where('company_id', $this->candidate->company_id)
            ->where('type', 'application')
            ->first();

        if (! $template) {
            return;
        }

        $mailer = new MicrosoftGraphMailer($this->candidate->company);

        $mailer->send(
            to: $this->candidate->email,
            subject: $this->replacePlaceholders($template->subject),
            body: $this->replacePlaceholders($template->body),
        );
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
