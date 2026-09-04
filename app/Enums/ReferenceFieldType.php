<?php

namespace App\Enums;

enum ReferenceFieldType: string
{
    case Date = 'date';
    case Text = 'text';
    case Textarea = 'textarea';
    case Radio = 'radio';

    public function label(): string
    {
        return match ($this) {
            self::Date => 'Date',
            self::Text => 'Text',
            self::Textarea => 'Textarea',
            self::Radio => 'Radio (choices)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
