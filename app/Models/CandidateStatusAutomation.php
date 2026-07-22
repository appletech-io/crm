<?php

namespace App\Models;

use BackedEnum;
use Carbon\CarbonInterface;
use Database\Factories\CandidateStatusAutomationFactory;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CandidateStatusAutomation extends Model
{
    /** @use HasFactory<CandidateStatusAutomationFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'conditions' => 'array',
    ];

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(CandidateStatus::class, 'candidate_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(CandidateStatus::class, 'to_candidate_status_id');
    }

    /**
     * Check whether the given candidate satisfies all of this automation's conditions.
     */
    public function isSatisfiedBy(Model $candidate): bool
    {
        foreach ($this->conditions as $condition) {
            if (! $this->evaluateCondition($candidate, $condition)) {
                return false;
            }
        }

        return true;
    }

    /** @param  array{field: string, operator?: string, value?: string|null}  $condition */
    private function evaluateCondition(Model $candidate, array $condition): bool
    {
        $field = $condition['field'];
        $value = $condition['value'] ?? null;

        return match ($condition['operator'] ?? 'filled') {
            'equals' => $this->evaluateEquals($candidate, $field, $value),
            'not_equals' => $this->evaluateNotEquals($candidate, $field, $value),
            'contains' => $this->evaluateContains($candidate, $field, $value),
            'before' => $this->evaluateDateComparison($candidate, $field, $value, fn (CarbonInterface $a, CarbonInterface $b): bool => $a->lt($b)),
            'after' => $this->evaluateDateComparison($candidate, $field, $value, fn (CarbonInterface $a, CarbonInterface $b): bool => $a->gt($b)),
            'days_since_at_least' => $this->evaluateDaysSinceAtLeast($candidate, $field, $value),
            default => $this->evaluateFilled($candidate, $field),
        };
    }

    /**
     * Plain attribute:   "first_name"       → must not be blank
     * Wildcard relation: "skills.*"         → relation must have at least one record
     */
    private function evaluateFilled(Model $candidate, string $field): bool
    {
        if (str_ends_with($field, '.*')) {
            $relation = rtrim($field, '.*');

            return $candidate->{$relation}()->exists();
        }

        return filled(data_get($candidate, $field));
    }

    /**
     * Plain attribute must match the given value exactly. Wildcard relation paths
     * (e.g. "skills.*") aren't comparable to a single value, so never match.
     */
    private function evaluateEquals(Model $candidate, string $field, ?string $value): bool
    {
        if (str_ends_with($field, '.*')) {
            return false;
        }

        return $this->comparableValue(data_get($candidate, $field)) === $this->comparableValue($value);
    }

    private function evaluateNotEquals(Model $candidate, string $field, ?string $value): bool
    {
        if (str_ends_with($field, '.*')) {
            return false;
        }

        return ! $this->evaluateEquals($candidate, $field, $value);
    }

    private function evaluateContains(Model $candidate, string $field, ?string $value): bool
    {
        if (str_ends_with($field, '.*') || blank($value)) {
            return false;
        }

        $haystack = $this->comparableValue(data_get($candidate, $field));

        if ($haystack === null) {
            return false;
        }

        return str_contains(mb_strtolower($haystack), mb_strtolower($value));
    }

    /** @param  callable(CarbonInterface, CarbonInterface): bool  $compare */
    private function evaluateDateComparison(Model $candidate, string $field, ?string $value, callable $compare): bool
    {
        $fieldDate = $this->resolveDate(data_get($candidate, $field));
        $valueDate = $this->resolveDate($value);

        if (! $fieldDate || ! $valueDate) {
            return false;
        }

        return $compare($fieldDate, $valueDate);
    }

    /**
     * True once at least the given number of days have elapsed since the date
     * held in $field. Used for automations that need to fire without the
     * candidate being saved — see the scheduled `automations:check-time-based`
     * command, which re-evaluates these on a timer.
     */
    private function evaluateDaysSinceAtLeast(Model $candidate, string $field, ?string $value): bool
    {
        $fieldDate = $this->resolveDate(data_get($candidate, $field));

        if (! $fieldDate || ! is_numeric($value)) {
            return false;
        }

        return $fieldDate->lte(now()->subDays((int) $value));
    }

    private function resolveDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (blank($value) || ! is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Exception) {
            return null;
        }
    }

    private function comparableValue(mixed $value): ?string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            is_bool($value) => $value ? '1' : '0',
            $value === null => null,
            default => (string) $value,
        };
    }
}
