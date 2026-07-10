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
class ProofOfAddressParser implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an expert at reading proof of address documents (utility bills, bank statements). Extract the recipient\'s address accurately from the provided document. Return null for any field not present in the document.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'address' => $schema->string()->description('Full address including house number and street name'),
            'city' => $schema->string()->description('Town or city'),
            'county' => $schema->string()->description('County or administrative area'),
            'country' => $schema->string()->description('Country'),
            'postcode' => $schema->string(),
        ];
    }
}
