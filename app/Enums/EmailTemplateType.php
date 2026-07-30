<?php

namespace App\Enums;

enum EmailTemplateType: string
{
    case General = 'general';
    case Custom = 'custom';
    case Application = 'application';
    case CandidateBookingConfirmation = 'candidate_booking_confirmation';
    case ClientBookingConfirmation = 'client_booking_confirmation';
    case PayrollConfirmation = 'payroll_confirmation';
    case ReferenceRequest = 'reference_request';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Custom => 'Custom Template',
            self::Application => 'Application Email',
            self::CandidateBookingConfirmation => 'Candidate Booking Confirmation Email',
            self::ClientBookingConfirmation => 'Client Booking Confirmation Email',
            self::PayrollConfirmation => 'Payroll Confirmation Email',
            self::ReferenceRequest => 'Reference Request Email',
        };
    }

    /**
     * The only placeholders the sending job for this template type actually
     * replaces — used to drive the template editor's placeholder list and to
     * keep it honest, since nothing else in the app enforces this.
     *
     * @return array<string, string> placeholder key (no braces) => description
     */
    public function placeholders(): array
    {
        return match ($this) {
            self::General => [],

            // Custom templates' placeholders depend on the template's audience
            // (candidate/client/both), not the type — see CustomTemplatePlaceholders.
            self::Custom => [],

            self::Application => [
                'firstname' => "The candidate's first name",
                'lastname' => "The candidate's last name",
                'candidate_name' => "The candidate's full name",
                'email' => "The candidate's email address",
                'application_link' => 'Link to the application form',
                'expiry_date' => 'The date the application link expires',
            ],

            self::CandidateBookingConfirmation => [
                'firstname' => "The candidate's first name",
                'lastname' => "The candidate's last name",
                'email' => "The candidate's email address",
                'candidate_name' => "The candidate's full name, with title",
                'job_title' => 'The booked job title',
                'start_date' => 'The booking start date',
                'booking_ref' => 'The booking reference number',
                'client_name' => "The client's name",
                'client_address' => "The client's address",
                'client_city' => "The client's city",
                'client_postcode' => "The client's postcode",
                'client_contact_name' => "The client's main contact name",
                'client_contact_phone' => "The client's main contact phone number",
                'company_phone' => "Your company's phone number",
                'day_breakdown' => 'A table of booked days, pay rates and times',
                'application_pdf_link' => 'Link to the booking confirmation PDF',
            ],

            self::ClientBookingConfirmation => [
                'client_contact_name' => 'The recipient contact name',
                'client_name' => "The client's name",
                'client_address' => "The client's address",
                'client_city' => "The client's city",
                'client_postcode' => "The client's postcode",
                'candidate_name' => "The candidate's full name",
                'job_title' => 'The booked job title',
                'start_date' => 'The booking start date',
                'booking_ref' => 'The booking reference number',
                'day_breakdown' => 'A table of booked days, charge rates and times',
                'application_pdf_link' => 'Link to the booking confirmation PDF',
            ],

            self::PayrollConfirmation => [
                'client_contact_name' => 'The recipient contact name',
                'client_name' => "The client's name",
                'week_start' => 'The start date of the timesheet period',
                'week_end' => 'The end date of the timesheet period',
                'day_breakdown' => 'A table of worked days, candidates and job titles',
                'payroll_confirmation_link' => 'Link to review and confirm the timesheet',
            ],

            self::ReferenceRequest => [
                'referee_name' => "The referee's full name",
                'candidate_name' => "The candidate's full name",
                'reference_link' => 'Link to the reference form',
                'expiry_date' => 'The date the reference link expires',
            ],
        };
    }
}
