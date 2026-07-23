<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasFieldSuggestions
{
    /**
     * Singular relations to expose as "relation.column" suggestions, keyed by
     * relation name, each holding the related model class and any of its own
     * columns to exclude (e.g. the foreign key back to this model).
     *
     * @return array<string, array{0: class-string<Model>, 1: array<int, string>}>
     */
    protected static function relationSuggestions(): array
    {
        return [];
    }

    /**
     * To-many relations exposed only as a "relation.*" wildcard suggestion,
     * satisfied by the relation having at least one record.
     *
     * @return array<int, string>
     */
    protected static function toManyRelationSuggestions(): array
    {
        return [];
    }

    /**
     * Fields available for automation conditions, keyed by dot-notation path,
     * with a human-readable label and an inferred value type.
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

        $relationColumns = collect(static::relationSuggestions())
            ->flatMap(fn (array $config, string $relation): array => static::relationFieldSuggestions(
                $relation,
                $config[0],
                $config[1] ?? [],
            ));

        $toManyRelations = collect(static::toManyRelationSuggestions())
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
        return Str::headline($column);
    }
}
