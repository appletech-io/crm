<?php

namespace App\Filament\Support;

use App\Models\CandidateStatus;
use App\Models\JobStatus;

/**
 * Maps the Filament color-name strings stored against job/candidate
 * statuses (see {@see JobStatus::COLOR_OPTIONS} and
 * {@see CandidateStatus::COLOR_OPTIONS}) to their hex
 * equivalents, for contexts that need a raw color (e.g. Chart.js
 * datasets) rather than a Filament Badge color name.
 */
class StatusColorPalette
{
    public static function hexFor(?string $color): string
    {
        return match ($color) {
            'red' => '#ef4444',
            'orange' => '#f97316',
            'amber' => '#f59e0b',
            'yellow' => '#eab308',
            'lime' => '#84cc16',
            'green' => '#22c55e',
            'emerald' => '#10b981',
            'teal' => '#14b8a6',
            'cyan' => '#06b6d4',
            'sky' => '#0ea5e9',
            'blue' => '#3b82f6',
            'indigo' => '#6366f1',
            'violet' => '#8b5cf6',
            'purple' => '#a855f7',
            'fuchsia' => '#d946ef',
            'pink' => '#ec4899',
            'rose' => '#f43f5e',
            default => '#94a3b8',
        };
    }
}
