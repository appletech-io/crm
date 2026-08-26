<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\EmailProvider;
use App\Enums\EmailTemplateType;
use App\Models\Booking;
use App\Models\ClientContact;
use App\Models\EmailTemplate;
use App\Services\Booking\BookingDayPeriods;
use App\Services\Education\BookingConfirmationLink;
use App\Services\Mail\Concerns\ReplacesEmailPlaceholders;
use App\Services\Mail\EmailFooter;
use App\Services\Mail\EmailSenderResolver;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\MicrosoftGraphMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendClientBookingConfirmationEmail implements ShouldQueue
{
    use Queueable;
    use ReplacesEmailPlaceholders;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Booking $booking,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $client = $this->booking->client;
        $contact = $this->recipientContact();

        if (! $client || ! $contact?->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('company_id', $this->booking->company_id)
            ->where('industry_id', 1)
            ->where('type', EmailTemplateType::ClientBookingConfirmation->value)
            ->first();

        if (! $template) {
            return;
        }

        try {
            $mailer = match ($client->company->email_provider) {
                EmailProvider::Mailgun => new MailgunMailer,
                default => new MicrosoftGraphMailer($client->company),
            };

            $replacements = $this->buildReplacements($contact);
            $sender = EmailSenderResolver::resolve($template, $client->consultant);

            $mailer->send(
                to: $contact->email,
                subject: $this->replacePlaceholders($template->subject ?? '', $replacements),
                body: $this->replacePlaceholders($template->body ?? '', $replacements).EmailFooter::render($client->company, $sender),
                from: $sender?->email ?? $client->company->defaultFromEmail(),
                attachments: [EmailFooter::logoAttachment($client->company)],
            );

            $client->activities()->create([
                'user_id' => null,
                'type' => ActivityType::Email->value,
                'note' => 'Booking confirmation sent',
                'body' => "Booking confirmation sent to {$contact->email}",
                'contacted' => true,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to send client booking confirmation email to {$contact->email}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function recipientContact(): ?ClientContact
    {
        return $this->booking->client?->bookingContact();
    }

    /** @return array<string, string> */
    private function buildReplacements(ClientContact $contact): array
    {
        $candidate = $this->booking->candidate;
        $client = $this->booking->client;

        $contactName = trim(collect([$contact->title, $contact->first_name, $contact->last_name])->filter()->implode(' '));

        $candidateName = trim(collect([$candidate?->first_name, $candidate?->last_name])->filter()->implode(' '));

        return [
            'client_contact_name' => $contactName,
            'client_name' => $client?->name ?? '',
            'client_address' => $client?->address ?? '',
            'client_city' => $client?->city ?? '',
            'client_postcode' => $client?->postcode ?? '',
            'candidate_name' => $candidateName,
            'job_title' => $this->booking->jobTitle?->name ?? '',
            'start_date' => $this->booking->start_date?->format('d-m-Y') ?? '',
            'booking_ref' => (string) $this->booking->id,
            'day_breakdown' => $this->dayBreakdownTable(),
            'application_pdf_link' => '<a href="'.BookingConfirmationLink::url($this->booking).'">Booking Confirmation</a>',
        ];
    }

    private function dayBreakdownTable(): string
    {
        $rows = BookingDayPeriods::rows($this->booking, 'charge')
            ->map(fn (array $row) => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $row['date']->format('d/m/Y'),
                $row['period']->label(),
                $row['start'],
                $row['cancelled'] ? 'Cancelled' : ($row['rate'] !== null ? '£'.number_format($row['rate'], 2) : '')
            ))
            ->implode('');

        return '<table><tr><th>Booking Date</th><th>Type</th><th>Start</th><th>Charge Rate</th></tr>'.$rows.'</table>';
    }
}
