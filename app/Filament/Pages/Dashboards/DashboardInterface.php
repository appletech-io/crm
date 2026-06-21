<?php

namespace App\Filament\Pages\Dashboards;

interface DashboardInterface
{
    public function getWidgets(): array;

    public function getTitle(): string;
}
