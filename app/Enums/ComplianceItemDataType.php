<?php

namespace App\Enums;

enum ComplianceItemDataType: string
{
    case Document = 'document';
    case Date = 'date';
    case DateExpiry = 'date_expiry';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Document => 'Document',
            self::Date => 'Date',
            self::DateExpiry => 'Date (with expiry tracking)',
            self::Text => 'Free text',
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
