<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Dashboards\ComplianceGenericDashboard;
use App\Filament\Pages\Dashboards\DashboardInterface;
use App\Filament\Pages\Dashboards\NoBookingsDashboard;
use App\Filament\Pages\Dashboards\NoSectorDashboard;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected ?DashboardInterface $dashboard = null;

    public function __construct()
    {
        $industry = active_industry();

        if (! $industry) {
            $this->dashboard = new NoSectorDashboard;

            return;
        }

        if (auth()->user()?->isComplianceOnly()) {
            $dashboardClass = 'App\\Filament\\Pages\\Dashboards\\Compliance'.ucfirst($industry).'Dashboard';

            if (class_exists($dashboardClass)) {
                $dashboard = app($dashboardClass);
                if ($dashboard instanceof DashboardInterface) {
                    $this->dashboard = $dashboard;
                }

                return;
            }

            $this->dashboard = app(ComplianceGenericDashboard::class);

            return;
        }

        // A sector with both Bookings and Perm on needs the user's own
        // preference to pick between the two; anything else collapses back
        // to a single side (see resolveForBothEnabled() and the two
        // branches below — "perm only" and "neither" both intentionally
        // land on NoBookingsDashboard, since it's already Vacancy/
        // Placement-derived rather than booking-derived).
        if (active_industry_uses_bookings() && active_industry_uses_perm()) {
            $this->dashboard = $this->resolveForBothEnabled($industry);

            return;
        }

        if (! active_industry_uses_bookings()) {
            $this->dashboard = app(NoBookingsDashboard::class);

            return;
        }

        $dashboardClass = 'App\\Filament\\Pages\\Dashboards\\'.ucfirst($industry).'Dashboard';

        if (class_exists($dashboardClass)) {
            $dashboard = app($dashboardClass);
            if ($dashboard instanceof DashboardInterface) {
                $this->dashboard = $dashboard;
            }
        }
    }

    /**
     * Falls back to NoBookingsDashboard (rather than leaving the industry
     * dashboard lookup return a blank page) when no bespoke {Industry}
     * Dashboard class exists — a small deliberate improvement over the
     * single-flag resolution above, which has always had that gap.
     */
    private function resolveForBothEnabled(string $industry): DashboardInterface
    {
        if (auth()->user()?->dashboard_view === 'perm') {
            return app(NoBookingsDashboard::class);
        }

        $dashboardClass = 'App\\Filament\\Pages\\Dashboards\\'.ucfirst($industry).'Dashboard';

        if (class_exists($dashboardClass)) {
            $dashboard = app($dashboardClass);
            if ($dashboard instanceof DashboardInterface) {
                return $dashboard;
            }
        }

        return app(NoBookingsDashboard::class);
    }

    public static function canAccess(): bool
    {
        return true;
    }

    /**
     * Only shown when a sector has both Bookings and Perm on — lets a user
     * flip which dashboard is their main view, persisted immediately as
     * their own preference (see resolveForBothEnabled()).
     */
    protected function getHeaderActions(): array
    {
        if (! (active_industry_uses_bookings() && active_industry_uses_perm())) {
            return [];
        }

        $viewingPerm = auth()->user()?->dashboard_view === 'perm';
        $target = $viewingPerm ? 'bookings' : 'perm';

        return [
            Action::make('switchDashboardView')
                ->label($viewingPerm ? 'Switch to Bookings view' : 'Switch to Perm view')
                ->icon('heroicon-o-arrows-right-left')
                ->action(function () use ($target): void {
                    auth()->user()?->update(['dashboard_view' => $target]);

                    $this->redirect(static::getUrl());
                }),
        ];
    }

    public function getActiveIndustry(): ?string
    {
        return active_industry();
    }

    public function getTitle(): string
    {
        if ($this->dashboard) {
            return $this->dashboard->getTitle();
        }

        return 'Dashboard';
    }

    public function getWidgets(): array
    {
        if ($this->dashboard) {
            return $this->dashboard->getWidgets();
        }

        return [];
    }

    /** @return int | array<string, ?int> */
    public function getColumns(): int|array
    {
        return $this->dashboard?->getColumns() ?? 2;
    }
}
