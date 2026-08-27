<?php

namespace App\Services\Candidates;

use App\Enums\ComplianceItemDataType;
use App\Models\Candidate;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Illuminate\Database\Eloquent\Model;

class CandidateDocumentRequirements
{
    private const string GET_DBS_URL = 'https://www.hr-platform.co.uk/individual/application-login/?oo5cmxwZZpKlDaAJsRQwuW5kwPSbJcpenhQ0jtA2nYJG7djU06QdfTBNKOJlBWY97U7ETKgKu4t0%2BzZZEKG4qMqhggknGonub5UYB0YG0rL5d1LwXgaeJZr2gIfegvXtvhL8jCnjUWWs4yVQcKvxUhu0gctiD7hHaBWpsSteUWGDq%2BUGkNNzHPqHGqPenD5K4TjY7L26P7mYOq%2FAPj%2F8WQ%3D%3D';

    /** @return array<string, array{document_type: string, label: string, description: string, uploaded: bool, path: ?string, url: ?string}> */
    public static function for(Model $candidate, bool $includeGetDbsAction = true): array
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
        ];

        if ($candidate instanceof EducationCandidate) {
            $definitions['safeguarding_training'] = [
                'label' => 'Safeguarding Training',
                'description' => 'Certificate confirming completion of safeguarding training.',
            ];

            $definitions['qualification'] = [
                'label' => 'Qualification',
                'description' => 'Evidence of your teaching qualification or certificate.',
            ];

            $definitions['benedicts_law'] = [
                'label' => 'Benedict\'s Law Certificate',
                'description' => 'Certificate confirming completion of allergy and anaphylaxis training required under Benedict\'s Law.',
            ];
        }

        if ($candidate instanceof HealthcareCandidate) {
            $definitions['professional_registration'] = [
                'label' => 'Professional Registration Certificate',
                'description' => 'Evidence of your current professional registration (e.g. NMC, HCPC).',
            ];
        }

        $definitions['proof_of_address'] = [
            'label' => 'Proof of Address',
            'description' => 'A recent utility bill or bank statement.',
        ];

        $definitions['proof_of_ni'] = [
            'label' => 'Proof of NI',
            'description' => 'A document confirming your National Insurance number.',
        ];

        // DBS/right-to-work isn't a fixed concept for the generic Candidate
        // model — that's exactly what the configurable Compliance Items
        // system covers for it instead, so this whole block (and the
        // has_dbs/has_naric columns it reads) doesn't apply.
        if (! $candidate instanceof Candidate) {
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

            $definitions['dbs_front'] = [
                'label' => 'DBS (Front)',
                'description' => 'The front of your DBS certificate or update service check.',
            ];

            $definitions['dbs_back'] = [
                'label' => 'DBS (Back)',
                'description' => 'The back of your DBS certificate or update service check.',
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
        }

        $rows = collect($definitions)
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
            });

        // A generic Candidate's document-type Compliance Item fields (e.g.
        // "Right to Work: Document Upload") are folded into this same list
        // — one page for every document the candidate needs to provide,
        // rather than splitting fixed documents and compliance documents
        // across two separate pages. These are backed by
        // CandidateComplianceValue, not CandidateDocument, so they're
        // resolved and merged in separately rather than through $existing
        // above — see CandidateDocumentManager's upload/remove handling for
        // the "compliance_field_{id}" document_type convention this relies on.
        if ($candidate instanceof Candidate) {
            $rows = $rows->merge(static::complianceDocumentRows($candidate));
        }

        return $rows->all();
    }

    /** @return array<string, array{document_type: string, label: string, description: string, uploaded: bool, path: ?string, url: ?string}> */
    private static function complianceDocumentRows(Candidate $candidate): array
    {
        return collect(ComplianceRequirements::for($candidate))
            ->flatMap(fn (array $check) => collect($check['fields'])
                ->filter(fn (array $fieldCheck): bool => $fieldCheck['field']->data_type === ComplianceItemDataType::Document)
                ->mapWithKeys(function (array $fieldCheck) use ($check): array {
                    $field = $fieldCheck['field'];
                    $value = $fieldCheck['value'];
                    $key = "compliance_field_{$field->id}";

                    return [$key => [
                        'document_type' => $key,
                        'label' => "{$check['item']->name}: {$field->name}",
                        'description' => $field->description ?? '',
                        'uploaded' => filled($value?->document_path),
                        'path' => $value?->document_path,
                        'url' => null,
                    ]];
                }))
            ->all();
    }
}
