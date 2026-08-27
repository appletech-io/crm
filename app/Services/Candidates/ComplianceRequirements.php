<?php

namespace App\Services\Candidates;

use App\Enums\ComplianceItemDataType;
use App\Models\Candidate;
use App\Models\CandidateComplianceValue;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use Carbon\CarbonInterface;

/**
 * The data-driven equivalent of Education/HealthcareCandidate's hardcoded
 * CandidateVettingRequirements — a generic candidate's required items come
 * from whatever Compliance Items are assigned to their job title, rather
 * than a fixed set of model columns. Each item can hold several fields (e.g.
 * "DBS" = DBS Number + Issue Date + Expiry Date) — an item is only complete
 * once every one of its fields is.
 */
class ComplianceRequirements
{
    /**
     * A value expiring within this many days is treated the same as one
     * that's already expired — matches Education/Healthcare's own
     * CandidateVettingRequirements threshold.
     */
    private const EXPIRY_WARNING_DAYS = 14;

    /**
     * @return array<int, array{
     *     item: ComplianceItem,
     *     fields: array<int, array{field: ComplianceItemField, value: ?CandidateComplianceValue, complete: bool}>,
     *     complete: bool,
     * }>
     */
    public static function for(Candidate $candidate): array
    {
        $items = $candidate->jobTitle?->complianceItems()->with('fields')->get() ?? collect();
        $values = $candidate->complianceValues()->get()->keyBy('compliance_item_field_id');

        return $items
            ->map(function (ComplianceItem $item) use ($values): array {
                $fields = $item->fields
                    ->map(function (ComplianceItemField $field) use ($values): array {
                        $value = $values->get($field->id);

                        return [
                            'field' => $field,
                            'value' => $value,
                            'complete' => static::isValueComplete($field, $value),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'item' => $item,
                    'fields' => $fields,
                    'complete' => collect($fields)->every(fn (array $check): bool => $check['complete']),
                ];
            })
            ->values()
            ->all();
    }

    public static function isComplete(Candidate $candidate): bool
    {
        return collect(static::for($candidate))->every(fn (array $check): bool => $check['complete']);
    }

    private static function isValueComplete(ComplianceItemField $field, ?CandidateComplianceValue $value): bool
    {
        if (! $value) {
            return false;
        }

        return match ($field->data_type) {
            ComplianceItemDataType::Document => filled($value->document_path),
            ComplianceItemDataType::Text => filled($value->text_value),
            ComplianceItemDataType::Date => filled($value->date_value),
            ComplianceItemDataType::DateExpiry => filled($value->date_value) && ! static::isExpiredOrExpiringSoon($value->date_value),
        };
    }

    private static function isExpiredOrExpiringSoon(?CarbonInterface $date): bool
    {
        return $date !== null && $date->lte(now()->addDays(self::EXPIRY_WARNING_DAYS));
    }
}
