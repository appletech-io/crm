<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\JobPipelineFlow;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * A standalone home for {@see JobPipelineFlow}, separate from the main
 * Dashboard — hidden for a company/sector that only ever does temp/day
 * bookings, via CompanyFeaturesForm's uses_perm toggle (mirrors
 * active_industry_uses_bookings() gating Bookings/Payroll for the reverse
 * case).
 */
class JobPipeline extends Page
{
    protected string $view = 'filament.pages.job-pipeline';

    /**
     * No on-page heading — the widget's own wizard-style status bar sits
     * directly under the panel's top bar instead, so the page makes the
     * most of its height rather than repeating "Job Pipeline" twice.
     */
    protected ?string $heading = '';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static ?string $navigationLabel = 'Job Pipeline';

    protected static ?int $navigationSort = -1;

    public static function canAccess(): bool
    {
        return active_industry() !== null && active_industry_uses_perm();
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<int, class-string> */
    public function getWidgets(): array
    {
        return [
            JobPipelineFlow::class,
        ];
    }
}
