<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Models\Client;
use App\Models\EducationCandidate;
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Models\MarketingCampaign;
use App\Models\User;
use App\Services\Mail\Concerns\ReplacesEmailPlaceholders;
use App\Services\Mail\CustomTemplatePlaceholders;
use App\Services\Mail\EmailBodyImages;
use App\Services\Mail\EmailFooter;
use App\Services\Mail\EmailSenderResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
     * $adHocAttachments are storage paths under EmailImageController::ADHOC_DIRECTORY
     * for files attached to this specific send only (see PruneAdhocEmailAttachments
     * for how those eventually get cleaned up).
     */
    public function __construct(
        public readonly ?EmailTemplate $template,
        public readonly EducationCandidate|HealthcareCandidate|Client $recipient,
        public readonly ?int $sentByUserId = null,
        public readonly ?string $adHocSubject = null,
        public readonly ?string $adHocBody = null,
        public readonly ?MarketingCampaign $campaign = null,
        /** @var array<int, string> */
        public readonly array $adHocAttachments = [],
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
        $sentBy = $this->sentByUserId ? User::find($this->sentByUserId) : null;
        $sender = EmailSenderResolver::resolve($this->template, $consultant, $sentBy);
        $replacements = CustomTemplatePlaceholders::resolve($this->recipient, $contact);

        try {
            $mailer = $company->mailer();
            $disk = Storage::disk(config('filesystems.default'));

            $subject = $this->replacePlaceholders($this->template?->subject ?? $this->adHocSubject ?? '', $replacements);
            $body = $this->replacePlaceholders($this->template?->body ?? $this->adHocBody ?? '', $replacements);

            // The raw $body (with signed email-images links, not cid:
            // references) is what gets stored for the campaign send record
            // below, so it still renders normally when viewed back in the CRM.
            $embeddedImages = EmailBodyImages::embedInline($body);

            $attachments = [EmailFooter::logoAttachment($company), ...$embeddedImages['attachments']];

            foreach ($this->adHocAttachments as $path) {
                if (! $disk->exists($path)) {
                    continue;
                }

                $attachments[] = [
                    'name' => basename($path),
                    'mimeType' => $disk->mimeType($path) ?: 'application/octet-stream',
                    'inline' => false,
                    'content' => $disk->get($path),
                ];
            }

            $mailer->send(
                to: $email,
                subject: $subject,
                body: $embeddedImages['body'].EmailFooter::render($company, $sender),
                from: $sender?->email ?? $company->defaultFromEmail(),
                attachments: $attachments,
            );

            // Ad-hoc images/attachments aren't deleted here: a bulk/campaign
            // send dispatches one of these jobs per recipient, all sharing
            // the same uploaded files, so the first job to finish would
            // otherwise delete a file the next one still needs. They're
            // swept up later by PruneAdhocEmailAttachments instead — a
            // saved template's images are never touched by that, since
            // those live under a different, permanent directory.
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
