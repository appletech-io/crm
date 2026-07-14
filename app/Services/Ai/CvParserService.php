<?php

namespace App\Services\Ai;

use App\Ai\Agents\CvParser;
use App\DTOs\CvExtraction;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Responses\StructuredAgentResponse;

class CvParserService
{
    public function parse(string $filePath): CvExtraction
    {
        /** @var StructuredAgentResponse $response */
        $response = (new CvParser)->prompt(
            'Please extract all candidate information from this CV.',
            attachments: [
                Document::fromPath($filePath),
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
}
