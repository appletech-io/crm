<?php

namespace App\Services\Candidates;

use App\Enums\ComplianceItemDataType;
use App\Models\Candidate;
use App\Models\CandidateComplianceValue;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\JobTitle;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The data-driven equivalent of Education/HealthcareCandidate's hardcoded
 * CandidateVettingRequirements — a generic candidate's compliance data comes
 * from configurable Compliance Items rather than a fixed set of model
 * columns. Each item can hold several fields (e.g. "DBS" = DBS Number +
 * Issue Date + Expiry Date) — an item is only complete once every one of its
 * fields is.
 *
 * A candidate can fill out and persist every Compliance Item that exists for
 * their company/industry, regardless of job title — see for(). Job titles
 * are only used to answer "is this candidate eligible to work as X?" on
 * demand — see forJobTitle() — which is what actually gates booking/placing
 * a candidate into a role (App\Services\Booking\BookingEligibility).
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
     * Every Compliance Item the candidate could fill out — independent of
     * their job title, since a candidate isn't limited to one role.
     *
     * @return array<int, array{
     *     item: ComplianceItem,
     *     fields: array<int, array{field: ComplianceItemField, value: ?CandidateComplianceValue, complete: bool}>,
     *     complete: bool,
     * }>
     */
    public static function for(Candidate $candidate): array
    {
        $items = ComplianceItem::query()
            ->where('company_id', $candidate->company_id)
            ->where('industry_id', $candidate->industry_id)
            ->with('fields')
            ->get();

        return static::checksFor($items, $candidate);
    }

    /**
     * Just the items a specific job title requires, with the candidate's
     * current values plugged in — "is this candidate eligible to work as
     * $jobTitle?".
     *
     * @return array<int, array{
     *     item: ComplianceItem,
     *     fields: array<int, array{field: ComplianceItemField, value: ?CandidateComplianceValue, complete: bool}>,
     *     complete: bool,
     * }>
     */
    public static function forJobTitle(Candidate $candidate, ?JobTitle $jobTitle): array
    {
        $items = $jobTitle?->complianceItems()->with('fields')->get() ?? collect();

        return static::checksFor($items, $candidate);
    }

    public static function isCompleteForJobTitle(Candidate $candidate, ?JobTitle $jobTitle): bool
    {
        return collect(static::forJobTitle($candidate, $jobTitle))->every(fn (array $check): bool => $check['complete']);
    }

    /** @param Collection<int, ComplianceItem> $items */
    private static function checksFor(Collection $items, Candidate $candidate): array
    {
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
