<?php

namespace App\Models\Traits;

use BackedEnum;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Shared by any model with a `conditions` array-cast column, where each condition
 * is shaped as `array{field: string, operator?: string, value?: string|null}`.
 */
trait EvaluatesConditions
{
    /**
     * Operators whose truth value can change purely from time elapsing, with
     * no record being saved — used by the scheduled `*-based` commands to
     * know which records need periodic re-checking.
     *
     * @var array<int, string>
     */
    private const TIME_BASED_OPERATORS = ['days_since_at_least', 'days_until_at_most'];

    /**
     * Check whether the given record satisfies all of this model's conditions.
     */
    public function isSatisfiedBy(Model $record): bool
    {
        foreach ($this->conditions as $condition) {
            if (! $this->evaluateCondition($record, $condition)) {
                return false;
            }
        }

        return true;
    }

    public function hasTimeBasedCondition(): bool
    {
        return collect($this->conditions)
            ->contains(fn (array $condition): bool => in_array($condition['operator'] ?? null, self::TIME_BASED_OPERATORS, true));
    }

    /** @param  array{field: string, operator?: string, value?: string|null}  $condition */
    private function evaluateCondition(Model $record, array $condition): bool
    {
        $field = $condition['field'];
        $value = $condition['value'] ?? null;

        return match ($condition['operator'] ?? 'filled') {
            'equals' => $this->evaluateEquals($record, $field, $value),
            'not_equals' => $this->evaluateNotEquals($record, $field, $value),
            'contains' => $this->evaluateContains($record, $field, $value),
            'before' => $this->evaluateDateComparison($record, $field, $value, fn (CarbonInterface $a, CarbonInterface $b): bool => $a->lt($b)),
            'after' => $this->evaluateDateComparison($record, $field, $value, fn (CarbonInterface $a, CarbonInterface $b): bool => $a->gt($b)),
            'days_since_at_least' => $this->evaluateDaysSinceAtLeast($record, $field, $value),
            'days_until_at_most' => $this->evaluateDaysUntilAtMost($record, $field, $value),
            'blank' => $this->evaluateBlank($record, $field),
            default => $this->evaluateFilled($record, $field),
        };
    }

    /**
     * Plain attribute:   "first_name"       → must not be blank
     * Wildcard relation: "skills.*"         → relation must have at least one record
     */
    private function evaluateFilled(Model $record, string $field): bool
    {
        if (str_ends_with($field, '.*')) {
            $relation = rtrim($field, '.*');

            return $record->{$relation}()->exists();
        }

        return filled(data_get($record, $field));
    }

    /**
     * The exact inverse of "filled" — a plain attribute must be empty, or a
     * wildcard relation must have no records at all. Needed rather than
     * relying on "equals" with an empty value, since the value input the
     * condition builder shows for equals/not_equals is always required.
     */
    private function evaluateBlank(Model $record, string $field): bool
    {
        return ! $this->evaluateFilled($record, $field);
    }

    /**
     * Plain attribute must match the given value exactly. Wildcard relation paths
     * (e.g. "skills.*") aren't comparable to a single value, so never match.
     */
    private function evaluateEquals(Model $record, string $field, ?string $value): bool
    {
        if (str_ends_with($field, '.*')) {
            return false;
        }

        return $this->comparableValue(data_get($record, $field)) === $this->comparableValue($value);
    }

    private function evaluateNotEquals(Model $record, string $field, ?string $value): bool
    {
        if (str_ends_with($field, '.*')) {
            return false;
        }

        return ! $this->evaluateEquals($record, $field, $value);
    }

    private function evaluateContains(Model $record, string $field, ?string $value): bool
    {
        if (str_ends_with($field, '.*') || blank($value)) {
            return false;
        }

        $haystack = $this->comparableValue(data_get($record, $field));

        if ($haystack === null) {
            return false;
        }

        return str_contains(mb_strtolower($haystack), mb_strtolower($value));
    }

    /** @param  callable(CarbonInterface, CarbonInterface): bool  $compare */
    private function evaluateDateComparison(Model $record, string $field, ?string $value, callable $compare): bool
    {
        $fieldDate = $this->resolveDate(data_get($record, $field));
        $valueDate = $this->resolveDate($value);

        if (! $fieldDate || ! $valueDate) {
            return false;
        }

        return $compare($fieldDate, $valueDate);
    }

    /**
     * True once at least the given number of days have elapsed since the date
     * held in $field. Used for conditions that need to fire without the record
     * being saved — see the scheduled `*-based` commands that re-evaluate these.
     */
    private function evaluateDaysSinceAtLeast(Model $record, string $field, ?string $value): bool
    {
        $fieldDate = $this->resolveDate(data_get($record, $field));

        if (! $fieldDate || ! is_numeric($value)) {
            return false;
        }

        return $fieldDate->lte(now()->subDays((int) $value));
    }

    /**
     * True once the date held in $field is at most the given number of days
     * away — including once it's already passed. Used for "X days before"
     * reminders (e.g. notify 30 days before a DBS expires); see the scheduled
     * `*-based` commands that re-evaluate these.
     */
    private function evaluateDaysUntilAtMost(Model $record, string $field, ?string $value): bool
    {
        $fieldDate = $this->resolveDate(data_get($record, $field));

        if (! $fieldDate || ! is_numeric($value)) {
            return false;
        }

        return $fieldDate->lte(now()->addDays((int) $value));
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
