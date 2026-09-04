<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Industry;
use App\Models\ReferenceForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds Applebough's real reference forms — transcribed 1:1 from the
 * hardcoded match arms in App\Services\References\ReferenceFormSchema,
 * which stays in place as the permanent fallback for references created
 * before dynamic forms existed. Re-running is safe (updateOrCreate keyed
 * on name / field key).
 */
class ReferenceFormSeeder extends Seeder
{
    private const YES_NO = ['Yes', 'No'];

    private const YES_NO_NA = ['Yes', 'No', 'N/A'];

    private const RATING = ['Excellent', 'Good', 'Average', 'Below Avg', 'Poor', 'N/A'];

    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $industry = Industry::where('slug', 'education')->firstOrFail();

        $this->seedForm($company->id, $industry->id, 'Agency', false, true, [
            ['label' => 'Worked From', 'field_type' => 'date', 'required' => true],
            ['label' => 'Worked To', 'field_type' => 'date', 'required' => true],
            [
                'label' => 'Please inform :company_name of any safeguarding, child protection or disciplinary issues relating to this candidate',
                'field_type' => 'radio', 'options' => self::YES_NO, 'required' => true,
            ],
            [
                'label' => 'Please provide details', 'field_type' => 'textarea', 'required' => true,
                'show_when_field_key' => 'please_inform_company_name_of_any_safeguarding_child_protection_or_disciplinary_issues_relating_to_this_candidate',
                'show_when_value' => 'Yes',
            ],
        ]);

        $this->seedForm($company->id, $industry->id, 'Academic', false, true, [
            ['label' => 'Known From', 'field_type' => 'date', 'required' => true],
            ['label' => 'Known To', 'field_type' => 'date', 'required' => true],
        ]);

        $this->seedForm($company->id, $industry->id, 'Character', false, false, [
            ['label' => 'Known From', 'field_type' => 'date', 'required' => true],
            ['label' => 'Known To', 'field_type' => 'date', 'required' => true],
            [
                'label' => 'Do you believe this candidate is suitable for the role?',
                'field_type' => 'radio', 'options' => self::YES_NO, 'required' => true,
            ],
            [
                'label' => 'Please provide more information', 'field_type' => 'textarea', 'required' => true,
                'show_when_field_key' => 'do_you_believe_this_candidate_is_suitable_for_the_role',
                'show_when_value' => 'No',
            ],
            ['label' => 'Additional Details', 'field_type' => 'textarea', 'required' => true],
        ]);

        $this->seedForm($company->id, $industry->id, 'Professional', false, true, [
            ['label' => 'Worked From', 'field_type' => 'date', 'required' => true],
            ['label' => 'Worked To', 'field_type' => 'date', 'required' => true],
            [
                'label' => 'Please inform :company_name of any safeguarding, child protection or disciplinary issues relating to this candidate',
                'field_type' => 'radio', 'options' => self::YES_NO, 'required' => true,
            ],
            [
                'label' => 'Please provide details', 'field_type' => 'textarea', 'required' => true,
                'show_when_field_key' => 'please_inform_company_name_of_any_safeguarding_child_protection_or_disciplinary_issues_relating_to_this_candidate',
                'show_when_value' => 'Yes',
            ],

            [
                'label' => 'Would you recommend this candidate for day-to-day/short term work?',
                'field_type' => 'radio', 'options' => self::YES_NO_NA, 'required' => true,
                'section_heading' => 'Recommendations and engagement',
            ],
            [
                'label' => 'Would you recommend this candidate for a long-term post?',
                'field_type' => 'radio', 'options' => self::YES_NO_NA, 'required' => true,
                'section_heading' => 'Recommendations and engagement',
            ],
            [
                'label' => 'Would you employ/engage this candidate again?',
                'field_type' => 'radio', 'options' => self::YES_NO_NA, 'required' => true,
                'section_heading' => 'Recommendations and engagement',
            ],

            [
                'label' => 'Interaction with Children', 'field_type' => 'radio', 'options' => self::RATING, 'required' => true,
                'section_heading' => 'Please rate the above named candidate in the following categories',
            ],
            [
                'label' => 'Ability to assist the teacher', 'field_type' => 'radio', 'options' => self::RATING, 'required' => true,
                'section_heading' => 'Please rate the above named candidate in the following categories',
            ],
            [
                'label' => 'Ability to work on own initiative', 'field_type' => 'radio', 'options' => self::RATING, 'required' => true,
                'section_heading' => 'Please rate the above named candidate in the following categories',
            ],
            [
                'label' => 'Relationships with Pupils', 'field_type' => 'radio', 'options' => self::RATING, 'required' => true,
                'section_heading' => 'Please rate the above named candidate in the following categories',
            ],
            [
                'label' => 'Relationships with Staff', 'field_type' => 'radio', 'options' => self::RATING, 'required' => true,
                'section_heading' => 'Please rate the above named candidate in the following categories',
            ],
            [
                'label' => 'Suitability for Supply Work', 'field_type' => 'radio', 'options' => self::RATING, 'required' => true,
                'section_heading' => 'Please rate the above named candidate in the following categories',
            ],
            [
                'label' => 'Timekeeping / Punctuality', 'field_type' => 'radio', 'options' => self::RATING, 'required' => true,
                'section_heading' => 'Please rate the above named candidate in the following categories',
            ],

            ['label' => 'In what capacity do you know the above named applicant?', 'field_type' => 'text', 'required' => true],
            ['label' => 'Details of any breaks in employment (if applicable)', 'field_type' => 'textarea', 'required' => false],
        ]);

        $this->seedForm($company->id, $industry->id, 'Gap / Statement', true, false, []);
    }

    /** @param array<int, array<string, mixed>> $fields */
    private function seedForm(
        int $companyId,
        int $industryId,
        string $name,
        bool $isStatementOnly,
        bool $needsPositionAndOrganisation,
        array $fields,
    ): void {
        $form = ReferenceForm::updateOrCreate(
            ['company_id' => $companyId, 'industry_id' => $industryId, 'name' => $name],
            [
                'is_statement_only' => $isStatementOnly,
                'needs_position_and_organisation' => $needsPositionAndOrganisation,
            ],
        );

        foreach ($fields as $index => $field) {
            $form->fields()->updateOrCreate(
                ['reference_form_id' => $form->id, 'key' => $field['key'] ?? Str::slug($field['label'], '_')],
                [
                    'label' => $field['label'],
                    'field_type' => $field['field_type'],
                    'options' => $field['options'] ?? null,
                    'required' => $field['required'],
                    'section_heading' => $field['section_heading'] ?? null,
                    'show_when_field_key' => $field['show_when_field_key'] ?? null,
                    'show_when_value' => $field['show_when_value'] ?? null,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
