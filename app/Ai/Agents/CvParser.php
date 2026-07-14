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
        return 'You are an expert CV parser. Extract candidate information accurately from the provided CV document. Return null for any field not present in the CV.';
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
        ];
    }
}
