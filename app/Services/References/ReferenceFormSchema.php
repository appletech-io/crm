<?php

namespace App\Services\References;

use App\Enums\ReferenceType;

/**
 * Defines the questions shown on the public reference form for each
 * {@see ReferenceType}. Kept data-driven (rather than one Blade view per
 * type) since several field shapes — yes/no with a conditional detail
 * textarea, and the Professional rating grid — repeat across types.
 */
class ReferenceFormSchema
{
    private const array YES_NO_OPTIONS = [
        'yes' => 'Yes',
        'no' => 'No',
    ];

    private const array YES_NO_NA_OPTIONS = [
        'yes' => 'Yes',
        'no' => 'No',
        'na' => 'N/A',
    ];

    private const array RATING_OPTIONS = [
        'excellent' => 'Excellent',
        'good' => 'Good',
        'average' => 'Average',
        'below_avg' => 'Below Avg',
        'poor' => 'Poor',
        'na' => 'N/A',
    ];

    /** @return array<int, array{heading: ?string, fields: array<int, array<string, mixed>>}> */
    public static function sectionsFor(ReferenceType $type): array
    {
        return match ($type) {
            ReferenceType::Agency => [
                [
                    'heading' => null,
                    'fields' => [
                        self::dateField('worked_from', 'Worked From'),
                        self::dateField('worked_to', 'Worked To'),
                        self::radioField('safeguarding_issues', 'Please inform Applebough Education of any safeguarding, child protection or disciplinary issues relating to this candidate', self::YES_NO_OPTIONS),
                        self::textareaField('safeguarding_details', 'Please provide details', showWhen: ['safeguarding_issues', 'yes']),
                    ],
                ],
            ],
            ReferenceType::Academic => [
                [
                    'heading' => null,
                    'fields' => [
                        self::dateField('worked_from', 'Known From'),
                        self::dateField('worked_to', 'Known To'),
                    ],
                ],
            ],
            ReferenceType::Character => [
                [
                    'heading' => null,
                    'fields' => [
                        self::dateField('worked_from', 'Known From'),
                        self::dateField('worked_to', 'Known To'),
                        self::radioField('suitable_for_role', 'Do you believe this candidate is suitable for the role?', self::YES_NO_OPTIONS),
                        self::textareaField('suitability_details', 'Please provide more information', showWhen: ['suitable_for_role', 'no']),
                    ],
                ],
            ],
            ReferenceType::Professional => [
                [
                    'heading' => null,
                    'fields' => [
                        self::dateField('worked_from', 'Worked From'),
                        self::dateField('worked_to', 'Worked To'),
                        self::radioField('safeguarding_issues', 'Please inform Applebough Education of any safeguarding, child protection or disciplinary issues relating to this candidate', self::YES_NO_OPTIONS),
                        self::textareaField('safeguarding_details', 'Please provide details', showWhen: ['safeguarding_issues', 'yes']),
                    ],
                ],
                [
                    'heading' => 'Recommendations and engagement',
                    'fields' => [
                        self::radioField('recommend_short_term', 'Would you recommend this candidate for day-to-day/short term work?', self::YES_NO_NA_OPTIONS),
                        self::radioField('recommend_long_term', 'Would you recommend this candidate for a long-term post?', self::YES_NO_NA_OPTIONS),
                        self::radioField('employ_again', 'Would you employ/engage this candidate again?', self::YES_NO_NA_OPTIONS),
                    ],
                ],
                [
                    'heading' => 'Please rate the above named candidate in the following categories',
                    'fields' => [
                        self::radioField('rating_interaction_with_children', 'Interaction with Children', self::RATING_OPTIONS),
                        self::radioField('rating_ability_to_assist_teacher', 'Ability to assist the teacher', self::RATING_OPTIONS),
                        self::radioField('rating_ability_to_work_on_own_initiative', 'Ability to work on own initiative', self::RATING_OPTIONS),
                        self::radioField('rating_relationships_with_pupils', 'Relationships with Pupils', self::RATING_OPTIONS),
                        self::radioField('rating_relationships_with_staff', 'Relationships with Staff', self::RATING_OPTIONS),
                        self::radioField('rating_suitability_for_supply_work', 'Suitability for Supply Work', self::RATING_OPTIONS),
                        self::radioField('rating_timekeeping_punctuality', 'Timekeeping / Punctuality', self::RATING_OPTIONS),
                    ],
                ],
                [
                    'heading' => null,
                    'fields' => [
                        self::textField('capacity_known', 'In what capacity do you know the above named applicant?'),
                        self::textareaField('employment_breaks', 'Details of any breaks in employment (if applicable)', required: false),
                    ],
                ],
            ],
        };
    }

    /**
     * Only Character references skip the position/organisation fields in the
     * confirmation section — everyone else is confirming who they are
     * professionally, not just personally.
     */
    public static function needsPositionAndOrganisation(ReferenceType $type): bool
    {
        return $type !== ReferenceType::Character;
    }

    /** @return array<string, array<int, string>> */
    public static function rulesFor(ReferenceType $type): array
    {
        $rules = [];

        foreach (self::sectionsFor($type) as $section) {
            foreach ($section['fields'] as $field) {
                $rules["answers.{$field['key']}"] = self::rulesForField($field);
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function attributeNamesFor(ReferenceType $type): array
    {
        $names = [];

        foreach (self::sectionsFor($type) as $section) {
            foreach ($section['fields'] as $field) {
                $names["answers.{$field['key']}"] = $field['label'];
            }
        }

        return $names;
    }

    /** @return array<int, string> */
    private static function rulesForField(array $field): array
    {
        $rules = [];

        if ($field['show_when'] ?? null) {
            [$dependsOn, $value] = $field['show_when'];
            $rules[] = "required_if:answers.{$dependsOn},{$value}";
            $rules[] = 'nullable';
        } else {
            $rules[] = $field['required'] ? 'required' : 'nullable';
        }

        $rules[] = match ($field['type']) {
            'date' => 'date',
            'radio' => 'in:'.implode(',', array_keys($field['options'])),
            default => 'string',
        };

        if (in_array($field['type'], ['text', 'textarea'], true)) {
            $rules[] = 'max:2000';
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    private static function dateField(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'date', 'required' => true];
    }

    /** @param array<string, string> $options
     * @return array<string, mixed> */
    private static function radioField(string $key, string $label, array $options): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'radio', 'options' => $options, 'required' => true];
    }

    /** @return array<string, mixed> */
    private static function textField(string $key, string $label, bool $required = true): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'text', 'required' => $required];
    }

    /**
     * @param  array{0: string, 1: string}|null  $showWhen
     * @return array<string, mixed>
     */
    private static function textareaField(string $key, string $label, ?array $showWhen = null, bool $required = true): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'textarea', 'required' => $required, 'show_when' => $showWhen];
    }
}
