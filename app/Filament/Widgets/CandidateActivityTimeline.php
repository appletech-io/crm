<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityType;
use App\Filament\Widgets\Concerns\HasActivityTimeline;
use Filament\Widgets\TableWidget;

class CandidateActivityTimeline extends TableWidget
{
    use HasActivityTimeline;

    protected int|string|array $columnSpan = 'full';

    /** @return array<int, ActivityType> */
    protected static function loggableTypes(): array
    {
        return [
            ActivityType::Call,
            ActivityType::Note,
            ActivityType::Meeting,
            ActivityType::Other,
            ActivityType::Interview,
        ];
    }
}
