<?php

namespace App\Enums;

enum ActionAssigneeType: string
{
    case Consultant = 'consultant';
    case Role = 'role';

    public function label(): string
    {
        return match ($this) {
            self::Consultant => 'The record\'s consultant',
            self::Role => 'Everyone with a role',
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
