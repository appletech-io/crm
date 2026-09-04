<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPayrollBookingsTable;
use App\Filament\Concerns\HasTimesheetPeriodNavigation;
use App\Models\Company;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * A read-only mirror of RunPayroll available to any user, not just admins —
 * always scoped to the viewer's own bookings (consultant_id = them),
 * regardless of role, so an admin viewing this page sees the same "my
 * bookings" view a consultant does rather than the whole company's. Shows
 * candidate/job title/date/session and whether the client has approved,
 * disputed, or not yet responded. No confirmation-sending or export
 * actions — this page is for seeing where things stand, not acting on them.
 */
class ViewPayroll extends Page implements HasTable
{
    use HasPayrollBookingsTable;
    use HasTimesheetPeriodNavigation;
    use InteractsWithTable;

    protected string $view = 'filament.pages.run-payroll';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payroll';

    protected static \UnitEnum|string|null $navigationGroup = null;

    // Places this directly after Jobs (and every other currently-unsorted
    // top-level item) in the sidebar, rather than mixed in among them.
    protected static ?int $navigationSort = 100;

    public static function canAccess(): bool
    {
        return true;
    }

    /**
     * Always "my bookings", even for an admin — deliberately does not use
     * Booking::visibleToCurrentUser() as-is, since that scope shows every
     * booking at the company to an admin. This page's whole point is a
     * personal view, so it skips that bypass and filters by consultant_id
     * unconditionally.
     */
    protected function scopePayrollBookingsQuery(Builder $query): Builder
    {
        return $query->forActiveIndustry()->where('consultant_id', auth()->id());
    }

    public function mount(): void
    {
        $this->goToCurrentPeriod();
    }

    public function getHeading(): ?string
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        $period = $this->currentPeriod();

        return $period['start']->format('jS M Y').' - '.$period['end']->format('jS M Y');
    }

    public function table(Table $table): Table
    {
        return $this->configurePayrollTable($table, $this->periodNavigationActions());
    }

    protected function periodCompany(): Company
    {
        return Auth::user()->company;
    }
}
