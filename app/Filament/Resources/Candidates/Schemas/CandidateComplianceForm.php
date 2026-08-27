<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Enums\ComplianceItemDataType;
use App\Models\Candidate;
use App\Models\CandidateComplianceValue;
use App\Models\ComplianceItemField;
use App\Services\Candidates\ComplianceRequirements;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Builds the compliance-filling form for a Candidate — a dynamic Wizard with
 * one Step per Compliance Item that exists for their company/industry
 * (independent of job title — see ComplianceRequirements::for()), containing
 * one field per that item's Compliance Item Fields, each field's type driven
 * by its own data_type. There's no fixed shape (unlike HealthcareVettingSteps/
 * VettingSteps): a field maps to a CandidateComplianceValue row, not a
 * column on the candidate itself, so schema building and value
 * hydration/saving are kept together here to agree on the "field_{id}"
 * field-naming convention between them.
 *
 * The Wizard here is a plain embedded component (not the page-level
 * HasWizard pattern EducationVetting/HealthcareVetting use) — it's just a
 * step-by-step way to present a dynamic set of items, not a dedicated
 * sign-off flow, so it submits via whichever page it's embedded in.
 *
 * Used both as the "Compliance" tab on the staff-facing CandidateResource
 * form and as the candidate's own self-service portal page
 * (App\Filament\EducationCandidate\Pages\Compliance) — kept here rather
 * than resource-scoped since it has two independent consumers.
 */
class CandidateComplianceForm
{
    public static function configure(Schema $schema, Candidate $record): Schema
    {
        return $schema->components(static::stepsFor($record));
    }

    /** @return array<int, Component> */
    public static function stepsFor(Candidate $record): array
    {
        $checks = ComplianceRequirements::for($record);

        if ($checks === []) {
            return [];
        }

        $steps = collect($checks)
            ->map(fn (array $check): Step => Step::make($check['item']->name)
                ->description($check['item']->description)
                ->schema(collect($check['fields'])
                    ->map(fn (array $fieldCheck): Component => static::fieldFor($fieldCheck['field']))
                    ->all()))
            ->values()
            ->all();

        return [
            Wizard::make($steps)
                // A plain Wizard::make() renders nothing at all in its last
                // step's footer unless a submit action is explicitly given —
                // without this, reaching the final step showed an empty
                // footer with no way to save. wire:click="save" rather than
                // a Filament Action object, since this form is shared by
                // three different page types (two EditRecord pages and a
                // plain self-service Page) that each already expose their
                // own working `save()` method — this works identically
                // against all three without needing page-specific wiring.
                ->submitAction(new HtmlString(Blade::render(
                    <<<'BLADE'
                        <x-filament::button type="button" wire:click="save" wire:loading.attr="disabled">
                            Save
                        </x-filament::button>
                        BLADE
                )))
                ->columnSpanFull(),
        ];
    }

    private static function fieldFor(ComplianceItemField $field): Component
    {
        $fieldName = static::fieldName($field->id);

        return match ($field->data_type) {
            ComplianceItemDataType::Document => FileUpload::make($fieldName)
                ->label($field->name)
                ->helperText($field->description)
                ->disk(config('filesystems.default'))
                ->directory('candidate-compliance'),
            ComplianceItemDataType::Date, ComplianceItemDataType::DateExpiry => DatePicker::make($fieldName)
                ->label($field->name)
                ->helperText($field->description)
                ->native(false),
            ComplianceItemDataType::Text => TextInput::make($fieldName)
                ->label($field->name)
                ->helperText($field->description),
        };
    }

    /** @return array<string, mixed> */
    public static function existingValues(Candidate $record): array
    {
        return collect(ComplianceRequirements::for($record))
            ->flatMap(fn (array $check) => collect($check['fields'])
                ->mapWithKeys(function (array $fieldCheck): array {
                    $field = $fieldCheck['field'];
                    $value = $fieldCheck['value'];
                    $fieldName = static::fieldName($field->id);

                    return [$fieldName => match ($field->data_type) {
                        ComplianceItemDataType::Document => $value?->document_path,
                        ComplianceItemDataType::Date, ComplianceItemDataType::DateExpiry => $value?->date_value?->toDateString(),
                        ComplianceItemDataType::Text => $value?->text_value,
                    }];
                }))
            ->all();
    }

    public static function saveValues(Candidate $record, array $data): void
    {
        // Built with an explicit loop rather than flatMap()->keyBy() —
        // flatMap collapses its sub-collections with array-merge semantics,
        // which silently renumbers purely-integer keys (field IDs) instead
        // of preserving them, scrambling which submitted value belongs to
        // which field.
        $fields = [];

        foreach (ComplianceRequirements::for($record) as $check) {
            foreach ($check['fields'] as $fieldCheck) {
                $fields[$fieldCheck['field']->id] = $fieldCheck['field'];
            }
        }

        foreach ($fields as $fieldId => $field) {
            $fieldName = static::fieldName($fieldId);

            if (! array_key_exists($fieldName, $data)) {
                continue;
            }

            $state = $data[$fieldName];

            $values = match ($field->data_type) {
                ComplianceItemDataType::Document => ['document_path' => $state, 'document_name' => $state ? basename($state) : null],
                ComplianceItemDataType::Date, ComplianceItemDataType::DateExpiry => ['date_value' => $state],
                ComplianceItemDataType::Text => ['text_value' => $state],
            };

            CandidateComplianceValue::updateOrCreate(
                ['candidate_id' => $record->id, 'compliance_item_field_id' => $fieldId],
                [...$values, 'completed_at' => filled($state) ? now() : null],
            );
        }
    }

    private static function fieldName(int $fieldId): string
    {
        return "field_{$fieldId}";
    }
}
