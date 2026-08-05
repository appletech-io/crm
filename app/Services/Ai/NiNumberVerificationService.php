<?php

namespace App\Services\Ai;

use App\Ai\Agents\ProofOfNiParser;
use App\DTOs\ProofOfNiExtraction;
use App\Enums\DocumentType;
use App\Services\Concerns\ResolvesAiAttachment;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

class NiNumberVerificationService
{
    use ResolvesAiAttachment;

    /**
     * Extract the National Insurance number from the candidate's uploaded
     * proof of NI document, compare it against their stored NI number, and
     * persist the outcome on the candidate record.
     */
    public function verify(Model $candidate): bool
    {
        $document = $candidate->documents()->where('document_type', DocumentType::ProofOfNi)->first();

        if (! $document) {
            throw new RuntimeException('Candidate has no proof of NI document to verify.');
        }

        $extraction = $this->parse($document->path);

        $matches = $this->matches($candidate, $extraction);

        $candidate->update([
            'ni_number_extracted' => $extraction->niNumber,
            'ni_number_match' => $matches ? 'yes' : 'no',
            'ni_number_checked_at' => now(),
        ]);

        return $matches;
    }

    private function parse(string $path): ProofOfNiExtraction
    {
        /** @var StructuredAgentResponse $response */
        $response = (new ProofOfNiParser)->prompt(
            'Please extract the National Insurance number from this document.',
            attachments: [
                $this->attachmentFor($path),
            ],
        );

        $extraction = new ProofOfNiExtraction;
        $extraction->niNumber = $response['niNumber'] ?? null;

        return $extraction;
    }

    private function matches(Model $candidate, ProofOfNiExtraction $extraction): bool
    {
        $extractedNi = $this->normalize($extraction->niNumber);
        $storedNi = $this->normalize($candidate->ni_number);

        return $extractedNi !== '' && $storedNi !== '' && $extractedNi === $storedNi;
    }

    private function normalize(?string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', $value ?? ''));
    }
}
