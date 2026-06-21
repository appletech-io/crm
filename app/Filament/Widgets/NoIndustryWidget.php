<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class NoIndustryWidget extends Widget
{
    protected string $view = 'filament.widgets.no-industry-widget';

    protected int | string | array $columnSpan = 'full';
}
