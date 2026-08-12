<?php

namespace App\Enums;

enum ActivityType: string
{
    case Email = 'email';
    case Note = 'note';

    case Meeting = 'meeting';

    case Call = 'call';

    case Interview = 'interview';
    case BdmCall = 'bdm_call';
    case Visit = 'visit';

    case Other = 'other';
    case StatusAutomation = 'status_automation';
    case StatusChange = 'status_change';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Note => 'Note',
            self::Meeting => 'Meeting',
            self::Call => 'Call',
            self::Interview => 'Interview',
            self::BdmCall => 'BDM Call',
            self::Visit => 'Visit',
            self::Other => 'Other',
            self::StatusAutomation => 'Status Automation',
            self::StatusChange => 'Status Change',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Email => 'heroicon-o-envelope',
            self::Note => 'heroicon-o-pencil-square',
            self::Meeting => 'heroicon-o-calendar-days',
            self::Call => 'heroicon-o-phone',
            self::Interview => 'heroicon-o-chat-bubble-left-right',
            self::BdmCall => 'heroicon-o-phone-arrow-up-right',
            self::Visit => 'heroicon-o-map-pin',
            self::Other => 'heroicon-o-ellipsis-horizontal-circle',
            self::StatusAutomation => 'heroicon-o-bolt',
            self::StatusChange => 'heroicon-o-flag',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Email => 'primary',
            self::Note => 'info',
            self::Call => 'success',
            self::Meeting => 'warning',
            self::Interview => 'info',
            self::BdmCall => 'success',
            self::Visit => 'warning',
            self::StatusAutomation => 'danger',
            self::StatusChange => 'warning',
            self::Other => 'gray',
        };
    }
}
