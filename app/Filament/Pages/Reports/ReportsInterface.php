<?php

namespace App\Filament\Pages\Reports;

interface ReportsInterface
{
    public function getWidgets(): array;

    public function getTitle(): string;

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array;
}
