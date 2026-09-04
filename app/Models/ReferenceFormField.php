<?php

namespace App\Models;

use App\Enums\ReferenceFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReferenceFormField extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'field_type' => ReferenceFieldType::class,
            'options' => 'array',
            'required' => 'boolean',
        ];
    }

    public function referenceForm(): BelongsTo
    {
        return $this->belongsTo(ReferenceForm::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $field): void {
            if (blank($field->key)) {
                $field->key = self::uniqueKeyFor($field);
            }
        });
    }

    /**
     * Slugified from the label, once, the first time this field is ever
     * saved — never regenerated afterwards, since it's the stable identifier
     * used both in candidate_references.answers (keyed by field key) and by
     * a sibling field's show_when_field_key. Renaming a field's label later
     * must not silently orphan either of those.
     */
    private static function uniqueKeyFor(self $field): string
    {
        $base = Str::slug($field->label, '_') ?: 'field';
        $key = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('reference_form_id', $field->reference_form_id)
                ->where('key', $key)
                ->when($field->exists, fn ($query) => $query->whereKeyNot($field->id))
                ->exists()
        ) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
