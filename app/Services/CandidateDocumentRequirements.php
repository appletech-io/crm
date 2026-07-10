<?php

namespace App\Services;

use App\Models\EducationCandidate;

class CandidateDocumentRequirements
{
    private const string GET_DBS_URL = 'https://www.hr-platform.co.uk/individual/application-login/?oo5cmxwZZpKlDaAJsRQwuW5kwPSbJcpenhQ0jtA2nYJG7djU06QdfTBNKOJlBWY97U7ETKgKu4t0%2BzZZEKG4qMqhggknGonub5UYB0YG0rL5d1LwXgaeJZr2gIfegvXtvhL8jCnjUWWs4yVQcKvxUhu0gctiD7hHaBWpsSteUWGDq%2BUGkNNzHPqHGqPenD5K4TjY7L26P7mYOq%2FAPj%2F8WQ%3D%3D';

    /** @return array<string, array{document_type: string, label: string, description: string, uploaded: bool, path: ?string, url: ?string}> */
    public static function for(EducationCandidate $candidate, bool $includeGetDbsAction = true): array
    {
        $existing = $candidate->documents()->get()->keyBy(fn ($document) => $document->document_type->value);

        $definitions = [
            'cv' => [
                'label' => 'CV',
                'description' => 'Your most recent CV.',
            ],
            'photo' => [
                'label' => 'Photo',
                'description' => 'A clear, recent photo of yourself.',
            ],
            'prevent_training' => [
                'label' => 'Prevent Training',
                'description' => 'Certificate confirming completion of Prevent duty training.',
            ],
            'safeguarding_training' => [
                'label' => 'Safeguarding Training',
                'description' => 'Certificate confirming completion of safeguarding training.',
            ],
            'proof_of_address' => [
                'label' => 'Proof of Address',
                'description' => 'A recent utility bill or bank statement.',
            ],
            'proof_of_ni' => [
                'label' => 'Proof of NI',
                'description' => 'A document confirming your National Insurance number.',
            ],
        ];

        match ($candidate->right_to_work_type) {
            'birth_certificate' => $definitions['birth_certificate'] = [
                'label' => 'Birth Certificate',
                'description' => 'Your birth certificate, as proof of your right to work.',
            ],
            'passport' => $definitions['passport'] = [
                'label' => 'Passport',
                'description' => 'Your passport, as proof of your right to work.',
            ],
            default => null,
        };

        $definitions['dbs'] = [
            'label' => 'DBS',
            'description' => 'Your DBS certificate or update service check.',
        ];

        if ($candidate->has_dbs === 'no') {
            $definitions['proof_of_address_2'] = [
                'label' => 'Proof of Address 2',
                'description' => 'A second, different proof of address, since you do not currently have a DBS.',
            ];

            if ($includeGetDbsAction) {
                // Prepended so it's the first, most obvious row when a DBS is still needed.
                $definitions = ['get_dbs' => [
                    'label' => 'Get your DBS',
                    'description' => 'You told us you do not currently have a DBS. Apply for one, then come back and upload it above.',
                    'url' => self::GET_DBS_URL,
                ]] + $definitions;
            }
        }

        if ($candidate->has_naric === 'yes') {
            $definitions['uk_naric'] = [
                'label' => 'UK NARIC',
                'description' => 'Your UK NARIC statement of comparability.',
            ];
        }

        return collect($definitions)
            ->map(function (array $definition, string $key) use ($existing): array {
                $document = $existing->get($key);

                return [
                    'document_type' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'uploaded' => $document !== null,
                    'path' => $document?->path,
                    'url' => $definition['url'] ?? null,
                ];
            })
            ->all();
    }
}
