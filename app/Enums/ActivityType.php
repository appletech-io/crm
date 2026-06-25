<?php

namespace App\Enums;

enum ActivityType: string
{
    case Email = 'email';
    case Note = 'note';

    case Meeting = 'meeting';

    case Call = 'call';

    case Other = 'other';
    case StatusAutomation = 'status_automation';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Note => 'Note',
            self::Meeting => 'Meeting',
            self::Call => 'Call',
            self::Other => 'Other',
            self::StatusAutomation => 'Status Automation',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Email => 'heroicon-o-envelope',
            self::Note => 'heroicon-o-pencil-square',
            self::Meeting => 'heroicon-o-calendar-days',
            self::Call => 'heroicon-o-phone',
            self::Other => 'heroicon-o-ellipsis-horizontal-circle',
            self::StatusAutomation => 'heroicon-o-bolt',
        };
    }
}
