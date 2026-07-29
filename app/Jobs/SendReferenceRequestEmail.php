<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\EmailProvider;
use App\Models\CandidateReference;
use App\Models\EmailTemplate;
use App\Models\Industry;
use App\Services\Mail\EmailFooter;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\MicrosoftGraphMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendReferenceRequestEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly CandidateReference $reference,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        if (! $this->reference->contact_now) {
            return;
        }

        if (blank($this->reference->email) || blank($this->reference->token)) {
            return;
        }

        $candidate = $this->reference->candidate;

        if (! $candidate) {
            return;
        }

        $industrySlug = Industry::slugForCandidateModel($candidate::class) ?? 'education';
        $industryId = Industry::where('slug', $industrySlug)->value('id');

        $template = EmailTemplate::query()
            ->where('company_id', $candidate->company_id)
            ->where('industry_id', $industryId)
            ->where('type', 'reference_request')
            ->first();

        if (! $template) {
            return;
        }

        try {
            $mailer = match ($candidate->company->email_provider) {
                EmailProvider::Mailgun => new MailgunMailer,
                default => new MicrosoftGraphMailer($candidate->company),
            };

            $mailer->send(
                to: $this->reference->email,
                subject: $this->replacePlaceholders($template->subject ?? ''),
                body: $this->replacePlaceholders($template->body ?? '').EmailFooter::render($candidate->company, $candidate->consultant),
                from: $candidate->consultant?->email ?? $candidate->company->defaultFromEmail(),
                attachments: [EmailFooter::logoAttachment()],
            );

            $candidate->activities()->create([
                'user_id' => $candidate->consultant_id,
                'type' => ActivityType::Email->value,
                'note' => 'Reference request sent',
                'body' => "Reference request email sent to {$this->refereeName()} ({$this->reference->email})",
                'contacted' => true,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to send reference request email to {$this->reference->email}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function refereeName(): string
    {
        return trim("{$this->reference->first_name} {$this->reference->last_name}");
    }

    private function replacePlaceholders(string $content): string
    {
        $candidate = $this->reference->candidate;

        $replacements = [
            '{referee_name}' => $this->refereeName(),
            '{candidate_name}' => trim("{$candidate->first_name} {$candidate->last_name}"),
            '{reference_link}' => route('reference.verify', ['token' => $this->reference->token]),
            '{expiry_date}' => $this->reference->expires_on?->format('d M Y') ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
