<?php

namespace App\Services;

use App\Ai\Agents\ProofOfAddressParser;
use App\DTOs\ProofOfAddressExtraction;
use App\Enums\DocumentType;
use App\Models\EducationCandidate;
use App\Services\Concerns\ResolvesAiAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

class ProofOfAddressVerificationService
{
    use ResolvesAiAttachment;

    /**
     * Extract the address from the candidate's uploaded proof of address
     * document, compare it against their stored address, and persist the
     * outcome on the candidate record.
     */
    public function verify(EducationCandidate $candidate): bool
    {
        $document = $candidate->documents()->where('document_type', DocumentType::ProofOfAddress)->first();

        if (! $document) {
            throw new RuntimeException('Candidate has no proof of address document to verify.');
        }

        $extraction = $this->parse(Storage::disk('local')->path($document->path));

        $matches = $this->matches($candidate, $extraction);

        $candidate->update([
            'proof_of_address_extracted' => collect([
                $extraction->address, $extraction->city, $extraction->county, $extraction->postcode, $extraction->country,
            ])->filter()->implode(', '),
            'proof_of_address_match' => $matches ? 'yes' : 'no',
            'proof_of_address_checked_at' => now(),
        ]);

        return $matches;
    }

    private function parse(string $filePath): ProofOfAddressExtraction
    {
        /** @var StructuredAgentResponse $response */
        $response = (new ProofOfAddressParser)->prompt(
            'Please extract the address from this proof of address document.',
            attachments: [
                $this->attachmentFor($filePath),
            ],
        );

        $extraction = new ProofOfAddressExtraction;
        $extraction->address = $response['address'] ?? null;
        $extraction->city = $response['city'] ?? null;
        $extraction->county = $response['county'] ?? null;
        $extraction->country = $response['country'] ?? null;
        $extraction->postcode = $response['postcode'] ?? null;

        return $extraction;
    }

    private function matches(EducationCandidate $candidate, ProofOfAddressExtraction $extraction): bool
    {
        $storedPostcode = $this->normalize($candidate->postcode);
        $extractedPostcode = $this->normalize($extraction->postcode);

        if ($storedPostcode === '' || $extractedPostcode === '' || $storedPostcode !== $extractedPostcode) {
            return false;
        }

        $storedAddress = $this->normalize($candidate->address);
        $extractedAddress = $this->normalize($extraction->address);

        if ($storedAddress === '' || $extractedAddress === '') {
            return false;
        }

        similar_text($storedAddress, $extractedAddress, $percent);

        return $percent >= 60.0;
    }

    private function normalize(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->replaceMatches('/[^a-z0-9 ]/', '')
            ->squish()
            ->toString();
    }
}
