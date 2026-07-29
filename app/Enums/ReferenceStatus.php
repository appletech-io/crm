<?php

namespace App\Enums;

enum ReferenceStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Contacted => 'Contacted',
            self::Submitted => 'Submitted',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Contacted => 'info',
            self::Submitted => 'warning',
            self::Confirmed => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Contacted => 'heroicon-o-chat-bubble-left-right',
            self::Submitted => 'heroicon-o-document-check',
            self::Confirmed => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Pending => '⏳',
            self::Contacted => '📞',
            self::Submitted => '📝',
            self::Confirmed => '✅',
            self::Rejected => '❌',
        };
    }
}
