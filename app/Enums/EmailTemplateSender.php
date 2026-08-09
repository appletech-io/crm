<?php

namespace App\Enums;

enum EmailTemplateSender: string
{
    case Consultant = 'consultant';
    case ComplianceOfficer = 'compliance_officer';

    public function label(): string
    {
        return match ($this) {
            self::Consultant => 'Consultant',
            self::ComplianceOfficer => 'Compliance Officer',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
