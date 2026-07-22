<?php

namespace App\Models\Traits;

use App\Models\Qualification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasCandidateFieldSuggestions
{
    /** @return class-string<Model> */
    abstract protected static function applicationModelClass(): string;

    /** @return array<int, string> */
    abstract protected static function applicationExcludedColumns(): array;

    /**
     * Fields available for status automation conditions, keyed by dot-notation
     * path, with a human-readable label and an inferred value type.
     *
     * @return array<string, array{label: string, type: string}>
     */
    public static function candidateFieldSuggestions(): array
    {
        $excluded = ['id', 'company_id', 'industry_id', 'created_at', 'updated_at', 'deleted_at'];

        $columns = collect(static::columnMetaFor((new static)->getTable()))
            ->reject(fn (array $meta, string $col): bool => in_array($col, $excluded))
            ->mapWithKeys(fn (array $meta, string $col): array => [$col => [
                'label' => static::humanizeLabel($col),
                'type' => $meta['type'],
            ]]);

        $relationColumns = collect([
            ...static::relationFieldSuggestions('application', static::applicationModelClass(), static::applicationExcludedColumns()),
            ...static::relationFieldSuggestions('qualification', Qualification::class),
        ]);

        $toManyRelations = collect(['skills'])
            ->mapWithKeys(fn (string $rel): array => ["{$rel}.*" => [
                'label' => static::humanizeLabel($rel),
                'type' => 'relation_exists',
            ]]);

        return $columns
            ->merge($relationColumns)
            ->merge($toManyRelations)
            ->all();
    }

    /**
     * @param  class-string<Model>  $relatedModel
     * @param  array<int, string>  $additionalExcluded
     * @return array<string, array{label: string, type: string}>
     */
    protected static function relationFieldSuggestions(string $relation, string $relatedModel, array $additionalExcluded = []): array
    {
        $excluded = [...['id', 'company_id', 'industry_id', 'created_at', 'updated_at', 'deleted_at'], ...$additionalExcluded];

        $relationLabel = static::humanizeLabel($relation);

        return collect(static::columnMetaFor((new $relatedModel)->getTable()))
            ->reject(fn (array $meta, string $col): bool => in_array($col, $excluded))
            ->mapWithKeys(fn (array $meta, string $col): array => ["{$relation}.{$col}" => [
                'label' => "{$relationLabel}: ".static::humanizeLabel($col),
                'type' => $meta['type'],
            ]])
            ->all();
    }

    /** @return array<string, array{type: string}> */
    protected static function columnMetaFor(string $table): array
    {
        return collect(Schema::getColumns($table))
            ->mapWithKeys(fn (array $column): array => [$column['name'] => [
                'type' => static::inferFieldType($column['type_name'], $column['type']),
            ]])
            ->all();
    }

    protected static function inferFieldType(string $typeName, string $type): string
    {
        return match (true) {
            $typeName === 'tinyint' && str_contains($type, '(1)') => 'boolean',
            $typeName === 'date' => 'date',
            in_array($typeName, ['datetime', 'timestamp']) => 'datetime',
            in_array($typeName, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double']) => 'numeric',
            default => 'string',
        };
    }

    protected static function humanizeLabel(string $column): string
    {
        return Str::of($column)->replace('_', ' ')->title()->toString();
    }
}
