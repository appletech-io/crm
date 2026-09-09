<?php

namespace App\Services\References;

use App\Enums\ReferenceFieldType;
use App\Models\CandidateReference;
use App\Models\ReferenceForm;
use App\Models\ReferenceFormField;
use Illuminate\Support\Str;

/**
 * The dynamic-forms successor to {@see ReferenceFormSchema} — builds a
 * schema snapshot from a live {@see ReferenceForm} (called once, when a
 * CandidateReference is created — see CandidateReferenceObserver), and
 * renders/validates a reference from whatever schema it ends up with,
 * falling back to the legacy hardcoded ReferenceFormSchema for references
 * created before dynamic forms existed (schema === null).
 *
 * Produces the exact same shape ReferenceFormSchema always has —
 * array<{heading: ?string, fields: array<{key,label,type,options?,
 * required,show_when?}>}> — so every existing consumer (the referee-facing
 * Livewire form, the PDF service) keeps rendering fields the same way.
 */
class ReferenceFormRenderer
{
    /** @return array<int, array{heading: ?string, fields: array<int, array<string, mixed>>}> */
    public static function snapshotFor(ReferenceForm $form, string $companyName): array
    {
        return self::groupIntoSections(
            $form->fields
                ->map(fn (ReferenceFormField $field): array => [
                    $field->section_heading ?: null,
                    self::fieldFor($field, $companyName),
                ])
                ->all()
        );
    }

    /**
     * The form builder's live preview pane renders from unsaved repeater
     * state, where there are no ReferenceFormField records to read yet. The
     * raw arrays are hydrated into throwaway (never-saved) models purely so
     * the draft goes through exactly the same casts and the same fieldFor()
     * mapping a saved form does — the preview can't drift from the real
     * thing if there's only one mapping.
     *
     * Half-finished questions are skipped rather than rendered broken: a
     * question with no label or no answer type yet isn't something a referee
     * could ever be shown.
     *
     * @param  array<mixed, mixed>  $fields
     * @return array<int, array{heading: ?string, fields: array<int, array<string, mixed>>}>
     */
    public static function snapshotForDraft(array $fields, string $companyName): array
    {
        $entries = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $type = ReferenceFieldType::tryFrom((string) ($field['field_type'] ?? ''));

            if ($label === '' || $type === null) {
                continue;
            }

            $draft = new ReferenceFormField([
                'label' => $label,
                'field_type' => $type,
                'options' => array_values(array_filter((array) ($field['options'] ?? []), 'is_string')),
                'required' => (bool) ($field['required'] ?? false),
                'section_heading' => ($field['section_heading'] ?? null) ?: null,
                'show_when_field_key' => ($field['show_when_field_key'] ?? null) ?: null,
                'show_when_value' => ($field['show_when_value'] ?? null) ?: null,
            ]);

            // The saved key is slugged from the label on first save (see
            // ReferenceFormField::uniqueKeyFor) and the builder's "Only Show
            // When..." select offers sibling labels slugged the same way, so
            // this is what makes a draft's conditionals line up.
            $draft->key = Str::slug($label, '_');

            $entries[] = [$draft->section_heading, self::fieldFor($draft, $companyName)];
        }

        return self::groupIntoSections($entries);
    }

    /**
     * Consecutive fields sharing a heading become one section; a blank
     * heading is treated as no heading at all.
     *
     * @param  array<int, array{0: ?string, 1: array<string, mixed>}>  $entries
     * @return array<int, array{heading: ?string, fields: array<int, array<string, mixed>>}>
     */
    private static function groupIntoSections(array $entries): array
    {
        $sections = [];
        $currentHeading = false;
        $currentFields = [];

        foreach ($entries as [$heading, $field]) {
            if ($heading !== $currentHeading) {
                if ($currentHeading !== false) {
                    $sections[] = ['heading' => $currentHeading, 'fields' => $currentFields];
                }

                $currentHeading = $heading;
                $currentFields = [];
            }

            $currentFields[] = $field;
        }

        if ($currentFields !== []) {
            $sections[] = ['heading' => $currentHeading, 'fields' => $currentFields];
        }

        return $sections;
    }

    /** @return array<string, mixed> */
    private static function fieldFor(ReferenceFormField $field, string $companyName): array
    {
        $options = null;

        if ($field->field_type === ReferenceFieldType::Radio) {
            $options = array_combine($field->options ?? [], $field->options ?? []);
        }

        return [
            'key' => $field->key,
            'label' => str_replace(':company_name', $companyName, $field->label),
            'type' => $field->field_type->value,
            'options' => $options,
            'required' => $field->required,
            'show_when' => $field->show_when_field_key
                ? [$field->show_when_field_key, $field->show_when_value]
                : null,
        ];
    }

    /** @return array<int, array{heading: ?string, fields: array<int, array<string, mixed>>}> */
    public static function sectionsFor(CandidateReference $reference, string $companyName): array
    {
        if ($reference->schema !== null) {
            return $reference->schema;
        }

        return $reference->type
            ? ReferenceFormSchema::sectionsFor($reference->type, $companyName)
            : [];
    }

    /** @return array<string, array<int, string>> */
    public static function rulesFor(CandidateReference $reference): array
    {
        $rules = [];

        foreach (self::sectionsFor($reference, '') as $section) {
            foreach ($section['fields'] as $field) {
                $rules["answers.{$field['key']}"] = self::rulesForField($field);
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function attributeNamesFor(CandidateReference $reference, string $companyName): array
    {
        $names = [];

        foreach (self::sectionsFor($reference, $companyName) as $section) {
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
}
