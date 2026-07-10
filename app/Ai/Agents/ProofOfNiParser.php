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
class ProofOfNiParser implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an expert at reading National Insurance documents (NI card, HMRC letter, payslip). Extract the National Insurance number accurately from the provided document. Return null if it is not present.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'niNumber' => $schema->string()->description('The National Insurance number, e.g. QQ123456C'),
        ];
    }
}
