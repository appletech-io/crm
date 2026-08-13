<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-4o')]
#[Timeout(180)]
class CvParser implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an expert CV parser. Extract candidate information accurately from the provided CV document. Return null for any field not present in the CV. '.
            'The other fields below ask for short, structured summaries (e.g. employmentHistory only wants a company name, job title, and dates) — bodyHtml is different: '.
            'it must be a full, detailed, verbatim reproduction of the CV, including every bullet point of responsibilities and achievements under each role. '.
            'Never collapse bodyHtml\'s employment section into a bare table of dates and titles.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'firstName' => $schema->string(),
            'middleName' => $schema->string()->description('Middle name if present in the CV'),
            'lastName' => $schema->string(),
            'email' => $schema->string()->description('Email address if present in the CV'),
            'dateOfBirth' => $schema->string()->description('Date of birth in YYYY-MM-DD format'),
            'address' => $schema->string()->description('Full address including house number and street name'),
            'city' => $schema->string()->description('Town or city'),
            'county' => $schema->string()->description('County or administrative area'),
            'country' => $schema->string()->description('Country of residence'),
            'postcode' => $schema->string(),
            'phone' => $schema->string()->description('Telephone or home phone number'),
            'mobile' => $schema->string()->description('Mobile phone number'),
            'gender' => $schema->string()->description('Gender if stated in the CV'),
            'nationality' => $schema->string()->description('Nationality if stated in the CV'),
            'employmentHistory' => $schema->array()
                ->description('List of previous jobs, most recent first')
                ->items(
                    $schema->object(fn ($schema) => [
                        'companyName' => $schema->string()->required(),
                        'jobTitle' => $schema->string()->required(),
                        'workedFrom' => $schema->string()->description('Start date in YYYY-MM-DD format. If only month/year is known, use the first day of the month.'),
                        'workedTo' => $schema->string()->description('End date in YYYY-MM-DD format. Leave null if this is their current job.'),
                    ])
                ),
            'educationAndQualification' => $schema->string()->description('Education qualifications and certifications as plain text'),
            'skills' => $schema->string()->description('Key skills and competencies as a comma-separated list'),
            'summary' => $schema->string()->description('Professional summary or personal statement from the CV'),
            'bodyHtml' => $schema->string()->description(
                'The candidate\'s ENTIRE CV content, reproduced faithfully and IN FULL as HTML (use <h2>/<h3> for '.
                'section headings, <p> for paragraphs, <ul>/<li> for bullet points) — every section, in the '.
                'original wording and order: personal profile/summary, complete employment history, education, '.
                'qualifications, certifications, skills, interests, and anything else present. Do not summarize, '.
                'shorten, condense into a table, or omit any content. '.
                "\n\n".
                'This is the most common mistake: for EVERY role in the employment history, you must include the '.
                'full bullet-pointed list of responsibilities and achievements exactly as written in the CV — '.
                'never just the job title, company, and dates. For example, one role must look like this, not a '.
                'single table row: '.
                '<h3>Software Developer, Acme Ltd (2020 - 2023)</h3><ul><li>Led the migration of the core API to '.
                'a microservices architecture</li><li>Mentored two junior developers</li><li>Reduced deployment '.
                'time by 40% by introducing CI/CD pipelines</li></ul>. If a role genuinely has no bullet points '.
                'in the source CV, write its duties as a short paragraph instead of a table row — a bare table of '.
                'dates and titles is only acceptable if the original CV itself contains nothing more than that. '.
                "\n\n".
                'The one exception to "reproduce everything": omit the candidate\'s name, email, phone, mobile '.
                'number, and postal address wherever they appear — do not include a contact-details section at '.
                'all, and do not mention these in headings either.'
            ),
        ];
    }
}
