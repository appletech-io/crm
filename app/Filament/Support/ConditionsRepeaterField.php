<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Builds the repeatable "field / condition / value" schema shared by any
 * automation that stores conditions in the shape used by
 * App\Models\Traits\EvaluatesConditions — field-suggestion driven, type-aware
 * operators, and a value input that switches component based on the selected
 * field's type.
 */
class ConditionsRepeaterField
{
    /**
     * @param  Closure(Get $get): array<string, array{label: string, type: string}>  $suggestionsResolver
     */
    public static function make(string $name, Closure $suggestionsResolver): Repeater
    {
        return Repeater::make($name)
            ->label('Conditions')
            ->helperText('All conditions must be true before this automation triggers.')
            ->schema([
                Select::make('field')
                    ->label('Field')
                    ->options(fn (Get $get): array => collect($suggestionsResolver($get))
                        ->map(fn (array $meta): string => $meta['label'])
                        ->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('operator', 'filled');
                        $set('value', null);
                    })
                    ->columnSpan(2),

                Select::make('operator')
                    ->label('Condition')
                    ->options(fn (Get $get): array => static::operatorOptionsFor(
                        $suggestionsResolver($get)[$get('field')]['type'] ?? 'string'
                    ))
                    ->default('filled')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('value', null))
                    ->columnSpan(1),

                Select::make('value')
                    ->label('Value')
                    ->options(['1' => 'True', '0' => 'False'])
                    ->visible(fn (Get $get): bool => static::valueKind($suggestionsResolver($get), $get('field'), $get('operator')) === 'boolean')
                    ->required(fn (Get $get): bool => static::valueKind($suggestionsResolver($get), $get('field'), $get('operator')) === 'boolean')
                    ->columnSpan(2),

                DatePicker::make('value')
                    ->label('Value')
                    ->visible(fn (Get $get): bool => static::valueKind($suggestionsResolver($get), $get('field'), $get('operator')) === 'date')
                    ->required(fn (Get $get): bool => static::valueKind($suggestionsResolver($get), $get('field'), $get('operator')) === 'date')
                    ->columnSpan(2),

                TextInput::make('value')
                    ->label('Value (days)')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => static::valueKind($suggestionsResolver($get), $get('field'), $get('operator')) === 'days')
                    ->required(fn (Get $get): bool => static::valueKind($suggestionsResolver($get), $get('field'), $get('operator')) === 'days')
                    ->columnSpan(2),

                TextInput::make('value')
                    ->label('Value')
                    ->visible(fn (Get $get): bool => static::valueKind($suggestionsResolver($get), $get('field'), $get('operator')) === 'text')
                    ->required(fn (Get $get): bool => static::valueKind($suggestionsResolver($get), $get('field'), $get('operator')) === 'text')
                    ->columnSpan(2),
            ])
            ->columns(5)
            ->defaultItems(0)
            ->addActionLabel('Add condition')
            ->itemLabel(fn (array $state, Get $get): ?string => filled($state['field'] ?? null)
                ? static::conditionLabel($state, $suggestionsResolver($get))
                : null)
            ->required()
            ->minItems(1)
            ->columnSpanFull();
    }

    /** @return array<string, string> */
    public static function operatorOptionsFor(string $type): array
    {
        return match ($type) {
            'boolean' => [
                'filled' => 'Is filled',
                'equals' => 'Equals',
                'not_equals' => 'Does not equal',
            ],
            'date', 'datetime' => [
                'filled' => 'Is filled',
                'equals' => 'Equals',
                'not_equals' => 'Does not equal',
                'before' => 'Before',
                'after' => 'After',
                'days_since_at_least' => 'At least X days ago',
            ],
            'relation_exists' => [
                'filled' => 'Is filled',
            ],
            default => [
                'filled' => 'Is filled',
                'equals' => 'Equals',
                'not_equals' => 'Does not equal',
                'contains' => 'Contains',
            ],
        };
    }

    /**
     * Which kind of value input a given field+operator combination needs, so
     * the right one of the repeater's conditionally-visible "value" fields
     * is shown.
     *
     * @param  array<string, array{label: string, type: string}>  $suggestions
     */
    public static function valueKind(array $suggestions, ?string $field, ?string $operator): ?string
    {
        if (blank($operator) || $operator === 'filled') {
            return null;
        }

        if ($operator === 'days_since_at_least') {
            return 'days';
        }

        $type = $suggestions[$field]['type'] ?? 'string';

        if ($type === 'boolean' && in_array($operator, ['equals', 'not_equals'], true)) {
            return 'boolean';
        }

        if (in_array($type, ['date', 'datetime'], true) && in_array($operator, ['equals', 'not_equals', 'before', 'after'], true)) {
            return 'date';
        }

        if (in_array($operator, ['equals', 'not_equals', 'contains'], true)) {
            return 'text';
        }

        return null;
    }

    /**
     * @param  array{field?: string, operator?: string, value?: string|null}  $condition
     * @param  array<string, array{label: string, type: string}>  $suggestions
     */
    public static function conditionLabel(array $condition, array $suggestions): string
    {
        $field = $condition['field'] ?? '';
        $label = $suggestions[$field]['label'] ?? $field;
        $operator = $condition['operator'] ?? 'filled';
        $value = static::displayValue($suggestions[$field]['type'] ?? 'string', $condition['value'] ?? null);

        return match ($operator) {
            'equals' => "{$label} = {$value}",
            'not_equals' => "{$label} ≠ {$value}",
            'contains' => "{$label} contains \"{$value}\"",
            'before' => "{$label} before {$value}",
            'after' => "{$label} after {$value}",
            'days_since_at_least' => "{$label} at least {$value} days ago",
            default => "{$label} is filled",
        };
    }

    public static function displayValue(string $type, ?string $value): string
    {
        if ($type === 'boolean') {
            return $value === '1' ? 'True' : 'False';
        }

        return (string) $value;
    }
}
