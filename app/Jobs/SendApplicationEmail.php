<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\EmailProvider;
use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareApplication;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\User;
use App\Services\Mail\Concerns\ReplacesEmailPlaceholders;
use App\Services\Mail\EmailFooter;
use App\Services\Mail\EmailSenderResolver;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\MicrosoftGraphMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendApplicationEmail implements ShouldQueue
{
    use Queueable;
    use ReplacesEmailPlaceholders;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Handles both Education and Healthcare candidates — the two sides only
     * differ in which industry their template/route belongs to, both
     * resolved dynamically below rather than needing two near-identical job
     * classes.
     *
     * $createdByUserId must be captured by the caller at dispatch time (e.g.
     * auth()->id()) — this job runs on a queue worker with no session, so
     * auth() inside handle() would always resolve to null.
     */
    public function __construct(
        public readonly EducationCandidate|HealthcareCandidate $candidate,
        public readonly EducationApplication|HealthcareApplication $application,
        public readonly ?int $createdByUserId = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $industryId = Industry::where('slug', $this->industrySlug())->value('id');

        $template = EmailTemplate::query()
            ->where('company_id', $this->candidate->company_id)
            ->where('industry_id', $industryId)
            ->where('type', 'application')
            ->first();

        if (! $template) {
            return;
        }

        try {
            $creator = $this->createdByUserId ? User::find($this->createdByUserId) : null;
            $sender = EmailSenderResolver::resolve($template, $this->candidate->consultant);

            $mailer = match ($this->candidate->company->email_provider) {
                EmailProvider::Mailgun => new MailgunMailer,
                default => new MicrosoftGraphMailer($this->candidate->company),
            };

            $replacements = $this->buildReplacements();

            $mailer->send(
                to: $this->candidate->email,
                subject: $this->replacePlaceholders($template->subject ?? '', $replacements),
                body: $this->replacePlaceholders($template->body ?? '', $replacements).EmailFooter::render($this->candidate->company, $sender),
                from: $sender?->email ?? $creator?->email ?? $this->candidate->company->defaultFromEmail(),
                attachments: [EmailFooter::logoAttachment($this->candidate->company)],
            );

            $this->candidate->activities()->create([
                'user_id' => $this->candidate->consultant_id ?? $this->createdByUserId,
                'type' => ActivityType::Email->value,
                'note' => 'Application pack sent',
                'body' => "Application email sent to {$this->candidate->email}",
                'contacted' => true,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to send application email to {$this->candidate->email}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function industrySlug(): string
    {
        return Industry::slugForCandidateModel($this->candidate::class) ?? 'education';
    }

    private function applicationRouteName(): string
    {
        return match ($this->candidate::class) {
            HealthcareCandidate::class => 'application.healthcare.form',
            default => 'application.form',
        };
    }

    /** @return array<string, string> */
    private function buildReplacements(): array
    {
        $applicationUrl = route($this->applicationRouteName(), ['token' => $this->application->token]);

        return [
            'firstname' => $this->candidate->first_name ?? '',
            'lastname' => $this->candidate->last_name ?? '',
            'candidate_name' => trim(($this->candidate->first_name ?? '').' '.($this->candidate->last_name ?? '')),
            'email' => $this->candidate->email ?? '',
            'application_link' => $applicationUrl,
            'expiry_date' => $this->application->expires_on?->format('d M Y') ?? '',
        ];
    }
}
