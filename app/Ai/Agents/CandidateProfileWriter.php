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
class CandidateProfileWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You write polished, professional candidate profiles for a recruitment agency to send to clients. '.
            'One or more sample profiles are attached — match their tone, structure, and level of formality as '.
            'closely as possible, treating them as the house style to follow. '.
            'Use ONLY the candidate facts given to you in the prompt — never invent, embellish, or assume any '.
            'employment history, qualification, skill, or achievement that was not actually provided. If very '.
            'little information is given, write a shorter profile rather than padding it with invented detail.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()
                ->description('A short professional summary paragraph introducing the candidate, in the sample profiles\' style — no HTML, plain prose.')
                ->required(),
            'experienceHtml' => $schema->string()
                ->description('The candidate\'s work history written up in the sample profiles\' style, as HTML (<h3>/<p>/<ul>/<li>). Use only the roles/dates/duties actually given — never invent one.'),
            'qualificationsHtml' => $schema->string()
                ->description('The candidate\'s qualifications and education, as HTML (<p>/<ul>/<li>). Use only what was actually given.'),
            'skillsHtml' => $schema->string()
                ->description('The candidate\'s key skills, as HTML (a <ul> of <li> items, or a short <p>). Use only the skills actually given.'),
        ];
    }
}
