<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\EmailProvider;
use App\Enums\EmailTemplateType;
use App\Models\EducationBooking;
use App\Models\EmailTemplate;
use App\Services\Education\BookingConfirmationLink;
use App\Services\Education\BookingDayPeriods;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\MicrosoftGraphMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBookingConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly EducationBooking $booking,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $candidate = $this->booking->education_candidate;

        if (! $candidate?->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('company_id', $this->booking->company_id)
            ->where('industry_id', 1)
            ->where('type', EmailTemplateType::CandidateBookingConfirmation->value)
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
                to: $candidate->email,
                subject: $this->replacePlaceholders($template->subject ?? ''),
                body: $this->replacePlaceholders($template->body ?? ''),
            );

            $candidate->activities()->create([
                'user_id' => $candidate->consultant_id ?? null,
                'type' => ActivityType::Email->value,
                'note' => 'Booking confirmation sent',
                'body' => "Booking confirmation sent to {$candidate->email}",
                'contacted' => true,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to send booking confirmation email to {$candidate->email}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function replacePlaceholders(string $content): string
    {
        $candidate = $this->booking->education_candidate;
        $client = $this->booking->education_client;

        $title = $candidate->title ? rtrim($candidate->title, '.').'.' : '';
        $candidateName = trim(collect([$title, $candidate->first_name, $candidate->last_name])->filter()->implode(' '));

        $mainContact = $client?->mainContact;
        $contactName = trim(collect([$mainContact?->title, $mainContact?->first_name, $mainContact?->last_name])->filter()->implode(' '));

        $replacements = [
            '{firstname}' => $candidate->first_name ?? '',
            '{lastname}' => $candidate->last_name ?? '',
            '{email}' => $candidate->email ?? '',
            '{candidate_name}' => $candidateName,
            '{job_title}' => $this->booking->jobTitle?->name ?? '',
            '{start_date}' => $this->booking->start_date?->format('jS M Y') ?? '',
            '{booking_ref}' => (string) $this->booking->id,
            '{client_name}' => $client?->name ?? '',
            '{client_address}' => $client?->address ?? '',
            '{client_city}' => $client?->city ?? '',
            '{client_postcode}' => $client?->postcode ?? '',
            '{client_contact_name}' => $contactName,
            '{client_contact_phone}' => $client?->phone ?? '',
            '{company_phone}' => $candidate->company->phone ?? '',
            '{day_breakdown}' => $this->dayBreakdownTable(),
            '{application_pdf_link}' => BookingConfirmationLink::url($this->booking),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private function dayBreakdownTable(): string
    {
        $rows = BookingDayPeriods::rows($this->booking, 'pay')
            ->map(fn (array $row) => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $row['date']->format('d/m/Y'),
                $row['period']->label(),
                $row['start'],
                $row['rate'] !== null ? '£'.number_format($row['rate'], 2) : ''
            ))
            ->implode('');

        return '<table><tr><th>Booking Date</th><th>Type</th><th>Start</th><th>Rate</th></tr>'.$rows.'</table>';
    }
}
