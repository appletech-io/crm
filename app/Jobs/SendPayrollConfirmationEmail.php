<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\EmailProvider;
use App\Enums\EmailTemplateType;
use App\Models\Booking;
use App\Models\BookingDay;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\EmailTemplate;
use App\Services\Booking\TimesheetPeriod;
use App\Services\Mail\MailgunMailer;
use App\Services\Mail\MicrosoftGraphMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPayrollConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Client $client,
        public readonly string $periodStart,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $contact = $this->recipientContact();

        if (! $contact?->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('company_id', $this->client->company_id)
            ->where('industry_id', 1)
            ->where('type', EmailTemplateType::PayrollConfirmation->value)
            ->first();

        if (! $template) {
            return;
        }

        $dayPeriods = $this->periodDayPeriods();

        if ($dayPeriods->isEmpty()) {
            return;
        }

        try {
            $mailer = match ($this->client->company->email_provider) {
                EmailProvider::Mailgun => new MailgunMailer,
                default => new MicrosoftGraphMailer($this->client->company),
            };

            $mailer->send(
                to: $contact->email,
                subject: $this->replacePlaceholders($template->subject ?? '', $contact, $dayPeriods),
                body: $this->replacePlaceholders($template->body ?? '', $contact, $dayPeriods),
                from: $this->client->consultant?->email ?? $this->client->company->defaultFromEmail(),
            );

            $dayPeriods->each->update(['payroll_confirmation_sent_at' => now()]);

            $dayPeriods->pluck('booking')->filter()->unique('id')
                ->each(fn (Booking $booking) => $booking->refreshPayrollStatus());

            $this->client->activities()->create([
                'user_id' => null,
                'type' => ActivityType::Email->value,
                'note' => 'Payroll confirmation sent',
                'body' => "Payroll confirmation sent to {$contact->email}",
                'contacted' => true,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to send payroll confirmation email to {$contact->email}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function recipientContact(): ?ClientContact
    {
        return $this->client->contacts()->where('timesheet_contact', true)->first()
            ?? $this->client->mainContact;
    }

    /** @return Collection<int, BookingDay> */
    private function periodDayPeriods(): Collection
    {
        $period = TimesheetPeriod::containing($this->client->company, Carbon::parse($this->periodStart));

        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->where('client_id', $this->client->id))
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->whereNull('cancelled_at')
            ->with(['booking.candidate', 'booking.jobTitle'])
            ->orderBy('date')
            ->get();
    }

    /** @param  Collection<int, BookingDay>  $dayPeriods */
    private function replacePlaceholders(string $content, ClientContact $contact, Collection $dayPeriods): string
    {
        $period = TimesheetPeriod::containing($this->client->company, Carbon::parse($this->periodStart));
        $contactName = trim(collect([$contact->title, $contact->first_name, $contact->last_name])->filter()->implode(' '));

        $replacements = [
            '{client_contact_name}' => $contactName,
            '{client_name}' => $this->client->name ?? '',
            '{week_start}' => $period['start']->format('d-m-Y'),
            '{week_end}' => $period['end']->format('d-m-Y'),
            '{day_breakdown}' => $this->dayBreakdownTable($dayPeriods),
            '{payroll_confirmation_link}' => '<a href="'.url('/client').'">Review & Confirm Timesheet</a>',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /** @param  Collection<int, BookingDay>  $dayPeriods */
    private function dayBreakdownTable(Collection $dayPeriods): string
    {
        $rows = $dayPeriods
            ->map(fn (BookingDay $dayPeriod): string => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                $dayPeriod->date->format('d/m/Y'),
                trim(collect([$dayPeriod->booking?->candidate?->first_name, $dayPeriod->booking?->candidate?->last_name])->filter()->implode(' ')),
                $dayPeriod->booking?->jobTitle?->name ?? ''
            ))
            ->implode('');

        return '<table><tr><th>Date</th><th>Candidate</th><th>Job Title</th></tr>'.$rows.'</table>';
    }
}
