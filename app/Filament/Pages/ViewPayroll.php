<?php

namespace App\Filament\Pages;

use App\Enums\PayrollStatusFilter;
use App\Filament\Concerns\HasPayrollBookingsTable;
use App\Filament\Concerns\HasTimesheetPeriodNavigation;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Company;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
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
 *
 * Framed as a chase list rather than a full record of the period: it opens
 * on the previous (finished) period, filtered to the days the client hasn't
 * signed off, so what's left on screen is what still needs chasing. Both are
 * defaults the consultant can navigate or filter their way out of, and
 * both are specific to this page — RunPayroll is untouched.
 */
class ViewPayroll extends Page implements HasTable
{
    use HasPayrollBookingsTable;
    use HasTimesheetPeriodNavigation;
    use InteractsWithTable;

    protected string $view = 'filament.pages.run-payroll';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Timesheets';

    // Kept in step with the navigation label so the browser tab doesn't say
    // "View Payroll" (what Filament would infer from the class name).
    protected static ?string $title = 'Timesheets';

    protected static \UnitEnum|string|null $navigationGroup = null;

    // Places this directly after Jobs (and every other currently-unsorted
    // top-level item) in the sidebar, rather than mixed in among them.
    protected static ?int $navigationSort = 100;

    /**
     * Open to every role except compliance-only users, who have no bookings
     * of their own and no part in the payroll process — same exclusion
     * BookingResource, ClientResource and VacancyResource apply.
     */
    public static function canAccess(): bool
    {
        return ! (auth()->user()?->isComplianceOnly() ?? false);
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

    /**
     * Opens on the previous period rather than the current one — the period
     * that has actually finished and been sent to clients for approval, so
     * a consultant lands straight on the clients still to approve last
     * period (last week, on a weekly company like Applebough) instead of a
     * period nobody has been asked to approve yet. The period navigation
     * actions still reach the current and any other period.
     */
    public function mount(): void
    {
        $this->goToCurrentPeriod();
        $this->goToPreviousPeriod();
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

    /**
     * Adds the status filter on top of the shared payroll table —
     * deliberately here rather than in HasPayrollBookingsTable, since
     * RunPayroll needs to keep showing every day in the period.
     */
    public function table(Table $table): Table
    {
        return $this->configurePayrollTable($table, $this->periodNavigationActions())
            ->filters([$this->payrollStatusFilter(), $this->clientFilter()], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->emptyStateHeading(fn (): string => $this->selectedStatusFilter()?->emptyStateHeading()
                ?? 'No bookings scheduled for this period');
    }

    /**
     * Defaults to the days still to be signed off, so the page opens as a
     * chase list — the reason a consultant comes here. Clearing the filter
     * (the "All" placeholder) shows the whole period.
     *
     * The three slices, and what each means as a query, live on
     * PayrollStatusFilter.
     */
    private function payrollStatusFilter(): SelectFilter
    {
        return SelectFilter::make('payroll_status')
            ->label('Status')
            ->placeholder('All')
            ->options(PayrollStatusFilter::options())
            ->default(PayrollStatusFilter::AwaitingApproval->value)
            ->query(fn (Builder $query, array $data): Builder => PayrollStatusFilter::tryFrom($data['value'] ?? '')
                ?->apply($query) ?? $query);
    }

    private function selectedStatusFilter(): ?PayrollStatusFilter
    {
        return PayrollStatusFilter::tryFrom($this->getTableFilterState('payroll_status')['value'] ?? '');
    }

    /**
     * Narrows to a single client. The table already groups by client, so
     * this is for zeroing in when a period spans more of them than fits on
     * a screen.
     */
    private function clientFilter(): SelectFilter
    {
        return SelectFilter::make('client_id')
            ->label('Client')
            ->placeholder('All clients')
            ->searchable()
            ->options(fn (): array => $this->clientOptions())
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                filled($data['value'] ?? null),
                fn (Builder $query) => $query->whereRelation('booking', 'client_id', $data['value']),
            ));
    }

    /**
     * Only the clients this consultant actually has bookings with, rather
     * than every client at the company — the same scope the table itself
     * uses, so the dropdown can't offer a client whose days would never
     * appear. Soft-deleted clients are included and marked as such, since
     * the table shows their days too: a client being deleted doesn't
     * unpay the candidates who worked there.
     *
     * @return array<int, string>
     */
    private function clientOptions(): array
    {
        return Client::withTrashed()
            ->whereIn('id', $this->scopePayrollBookingsQuery(Booking::query())->select('client_id'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Client $client): array => [
                $client->id => $client->trashed() ? "{$client->name} (deleted)" : $client->name,
            ])
            ->all();
    }

    protected function periodCompany(): Company
    {
        return Auth::user()->company;
    }
}
