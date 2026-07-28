<?php

namespace App\Filament\Pages\Dashboards;

interface DashboardInterface
{
    public function getWidgets(): array;

    public function getTitle(): string;

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array;
}
