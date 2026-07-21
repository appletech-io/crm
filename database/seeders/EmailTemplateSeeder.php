<?php

namespace Database\Seeders;

use App\Enums\EmailTemplateType;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Industry;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $industry = Industry::where('slug', 'education')->firstOrFail();

        $templates = [
            EmailTemplateType::General->value => [
                'subject' => 'A message from Applebough Education Recruitment',
                'body' => '<p>Hi {candidate_name},</p><p>Thanks for staying in touch with Applebough Education Recruitment. We wanted to update you on the latest opportunities available.</p><p>Best wishes,<br>The Applebough Team</p>',
            ],
            EmailTemplateType::Application->value => [
                'subject' => 'Complete your application with Applebough',
                'body' => '<p>Hi {candidate_name},</p><p>Thanks for registering with us. Please complete your application using the link below so we can start finding you the right placements.</p><p>{application_pdf_link}</p><p>Best wishes,<br>The Applebough Team</p>',
            ],
            EmailTemplateType::CandidateBookingConfirmation->value => [
                'subject' => 'Booking Confirmation — {job_title} at {client_name}',
                'body' => '<p>Dear {candidate_name},</p><p>This confirms your booking as <strong>{job_title}</strong> at <strong>{client_name}</strong>, starting {start_date}.</p><p>{client_address}, {client_city}, {client_postcode}<br>Site contact: {client_contact_name} ({client_contact_phone})</p><table>{day_breakdown}</table><p>Booking reference: {booking_ref}</p><p>A copy of this confirmation is available here: {application_pdf_link}</p><p>If you have any questions, call us on {company_phone}.</p><p>Best wishes,<br>The Applebough Team</p>',
            ],
            EmailTemplateType::ClientBookingConfirmation->value => [
                'subject' => 'Booking Confirmation — {candidate_name} for {job_title}',
                'body' => '<p>Dear {client_contact_name},</p><p>This confirms the booking of <strong>{candidate_name}</strong> as <strong>{job_title}</strong> at {client_name}, starting {start_date}.</p><table>{day_breakdown}</table><p>Booking reference: {booking_ref}</p><p>{application_pdf_link}</p><p>Best wishes,<br>The Applebough Team</p>',
            ],
            EmailTemplateType::PayrollConfirmation->value => [
                'subject' => 'Timesheet for {client_name} — {week_start} to {week_end}',
                'body' => '<p>Dear {client_contact_name},</p><p>Please review and confirm the timesheet for {client_name} covering {week_start} to {week_end}.</p><table><tr><th>Date</th><th>Candidate</th><th>Job Title</th></tr>{day_breakdown}</table><p>{payroll_confirmation_link}</p><p>Best wishes,<br>The Applebough Team</p>',
            ],
        ];

        foreach ($templates as $typeValue => $content) {
            $type = EmailTemplateType::from($typeValue);

            EmailTemplate::firstOrCreate([
                'company_id' => $company->id,
                'industry_id' => $industry->id,
                'type' => $type->value,
            ], [
                'name' => $type->label(),
                'subject' => $content['subject'],
                'body' => $content['body'],
            ]);
        }
    }
}
