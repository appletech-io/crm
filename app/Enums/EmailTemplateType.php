<?php

namespace App\Enums;

enum EmailTemplateType: string
{
    case General = 'general';
    case Application = 'application';
    case CandidateBookingConfirmation = 'candidate_booking_confirmation';
    case ClientBookingConfirmation = 'client_booking_confirmation';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Application => 'Application Email',
            self::CandidateBookingConfirmation => 'Candidate Booking Confirmation Email',
            self::ClientBookingConfirmation => 'Client Booking Confirmation Email',
        };
    }
}
