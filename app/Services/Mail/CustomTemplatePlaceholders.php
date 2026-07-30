<?php

namespace App\Services\Mail;

use App\Enums\EmailTemplateAudience;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;

/**
 * Single source of truth for Custom email templates' placeholders — used both
 * to drive the template editor's hint list ({@see self::definitions()}) and
 * to resolve the real values when previewing or sending
 * ({@see self::resolve()}), so the two can never drift out of sync.
 */
class CustomTemplatePlaceholders
{
    /** @var array<string, string> Safe for any recipient — candidate or client contact. */
    private const array SHARED = [
        'recipient_first_name' => "The recipient's first name",
        'recipient_last_name' => "The recipient's last name",
        'recipient_name' => "The recipient's full name",
        'recipient_email' => "The recipient's email address",
        'consultant_name' => "The assigned consultant's name",
        'company_name' => "Your company's name",
        'company_phone' => "Your company's phone number",
        'today_date' => "Today's date",
    ];

    /** @var array<string, string> Only valid when audience = Client (a specific Client is guaranteed). */
    private const array CLIENT_ONLY = [
        'client_name' => "The client's name",
        'client_address' => "The client's address",
        'client_city' => "The client's city",
        'client_postcode' => "The client's postcode",
    ];

    /** @return array<string, string> placeholder key (no braces) => description */
    public static function definitions(EmailTemplateAudience $audience): array
    {
        return match ($audience) {
            EmailTemplateAudience::Candidate, EmailTemplateAudience::Both => self::SHARED,
            EmailTemplateAudience::Client => [...self::SHARED, ...self::CLIENT_ONLY],
        };
    }

    /** @return array<string, string> placeholder key (no braces) => real value */
    public static function resolve(
        EducationCandidate|HealthcareCandidate|Client $recipient,
        ?ClientContact $contact = null,
    ): array {
        $isClient = $recipient instanceof Client;

        $name = $isClient
            ? trim(collect([$contact?->title, $contact?->first_name, $contact?->last_name])->filter()->implode(' '))
            : trim(collect([$recipient->first_name, $recipient->last_name])->filter()->implode(' '));

        $values = [
            'recipient_first_name' => $isClient ? ($contact?->first_name ?? '') : ($recipient->first_name ?? ''),
            'recipient_last_name' => $isClient ? ($contact?->last_name ?? '') : ($recipient->last_name ?? ''),
            'recipient_name' => $name,
            'recipient_email' => $isClient ? ($contact?->email ?? '') : ($recipient->email ?? ''),
            'consultant_name' => $recipient->consultant?->name ?? '',
            'company_name' => $recipient->company?->name ?? '',
            'company_phone' => $recipient->company?->phone ?? '',
            'today_date' => now()->format('d-m-Y'),
        ];

        if ($isClient) {
            $values += [
                'client_name' => $recipient->name ?? '',
                'client_address' => $recipient->address ?? '',
                'client_city' => $recipient->city ?? '',
                'client_postcode' => $recipient->postcode ?? '',
            ];
        }

        return $values;
    }
}
