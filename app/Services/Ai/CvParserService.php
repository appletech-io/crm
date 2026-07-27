<?php

namespace App\Services\Ai;

use App\Ai\Agents\CvParser;
use App\DTOs\CvExtraction;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Responses\StructuredAgentResponse;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\IOFactory;

class CvParserService
{
    public function parse(string $filePath): CvExtraction
    {
        /** @var StructuredAgentResponse $response */
        $response = (new CvParser)->prompt(
            'Please extract all candidate information from this CV.',
            attachments: [
                $this->attachmentFor($filePath),
            ],
        );

        $extraction = new CvExtraction;
        $extraction->firstName = $response['firstName'] ?? null;
        $extraction->middleName = $response['middleName'] ?? null;
        $extraction->lastName = $response['lastName'] ?? null;
        $extraction->email = $response['email'] ?? null;
        $extraction->dateOfBirth = $response['dateOfBirth'] ?? null;
        $extraction->address = $response['address'] ?? null;
        $extraction->city = $response['city'] ?? null;
        $extraction->county = $response['county'] ?? null;
        $extraction->country = $response['country'] ?? null;
        $extraction->postcode = $response['postcode'] ?? null;
        $extraction->phone = $response['phone'] ?? null;
        $extraction->mobile = $response['mobile'] ?? null;
        $extraction->gender = $response['gender'] ?? null;
        $extraction->nationality = $response['nationality'] ?? null;
        $extraction->employmentHistory = $response['employmentHistory'] ?? [];
        $extraction->educationAndQualification = $response['educationAndQualification'] ?? null;
        $extraction->summary = $response['summary'] ?? null;
        $extraction->skills = $response['skills'] ?? null;

        return $extraction;
    }

    /**
     * Word documents are extracted to plain text before being sent to the AI.
     * The model's document input is built around rendering PDF pages, and a
     * raw .docx (a binary zip/XML container) sent the same way would not be
     * understood — it wouldn't error, it would just silently extract nothing.
     */
    private function attachmentFor(string $filePath): Document
    {
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'docx') {
            return Document::fromString($this->extractDocxText($filePath), 'text/plain');
        }

        return Document::fromPath($filePath);
    }

    private function extractDocxText(string $filePath): string
    {
        $document = IOFactory::load($filePath, 'Word2007');

        $text = '';

        foreach ($document->getSections() as $section) {
            $text .= $this->extractContainerText($section);
        }

        return $text;
    }

    private function extractContainerText(AbstractContainer $container): string
    {
        $text = '';

        foreach ($container->getElements() as $element) {
            if (method_exists($element, 'getText')) {
                $value = $element->getText();
                $text .= (is_string($value) ? $value : '')."\n";
            } elseif ($element instanceof AbstractContainer) {
                $text .= $this->extractContainerText($element);
            }
        }

        return $text;
    }
}
