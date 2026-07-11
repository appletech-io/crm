<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasActivityTimeline;
use Filament\Widgets\TableWidget;

class ClientActivityTimeline extends TableWidget
{
    use HasActivityTimeline;

    protected int|string|array $columnSpan = 'full';
}
