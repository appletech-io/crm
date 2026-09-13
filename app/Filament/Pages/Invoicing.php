<?php

namespace App\Filament\Pages;

use App\Actions\Payroll\GenerateClientInvoices;
use App\Actions\Payroll\GenerateUmbrellaInvoices;
use App\Filament\Concerns\HasTimesheetPeriodNavigation;
use App\Filament\Support\FactoringScheduleCsvAction;
use App\Filament\Support\HmrcIntermediariesReportCsvAction;
use App\Filament\Support\PayeExportCsvAction;
use App\Models\BookingDay;
use App\Models\Company;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Proof-of-concept for running payroll in-house instead of via a third-party
 * provider like Evertime: once a period's timesheets are approved (see
 * RunPayroll), generate the actual financial outputs ourselves — client
 * invoices, umbrella-company self-bills, a factoring-company schedule, a
 * PAYE file, and an HMRC intermediaries report. Only shows *approved* days
 * for the period, since nothing here should be generated off unconfirmed
 * timesheets.
 */
class Invoicing extends Page implements HasTable
{
    use HasTimesheetPeriodNavigation;
    use InteractsWithTable;

    protected string $view = 'filament.pages.invoicing';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyPound;

    protected static ?string $navigationLabel = 'Invoicing';

    protected static \UnitEnum|string|null $navigationGroup = 'Payroll';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
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

        return $period['start']->format('jS M Y').' - '.$period['end']->format('jS M Y').' — approved days only';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->approvedDayPeriodsQuery())
            ->groups([
                Group::make('booking.client_id')
                    ->label('Client')
                    ->getTitleFromRecordUsing(fn (BookingDay $record): string => $this->clientLabel($record))
                    ->collapsible(),
            ])
            ->defaultGroup('booking.client_id')
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('candidate_name')
                    ->label('Candidate')
                    ->getStateUsing(fn (BookingDay $record): string => $this->candidateLabel($record)),
                TextColumn::make('booking.jobTitle.name')
                    ->label('Job Title')
                    ->placeholder('—'),
                TextColumn::make('date')
                    ->label('Date')
                    ->date('D jS M Y')
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Session')
                    ->formatStateUsing(fn ($state): string => $state->label()),
            ])
            ->headerActions([
                ...$this->periodNavigationActions(),
                Action::make('generateClientInvoices')
                    ->label('Generate Client Invoices')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Creates one invoice per client with approved days this period and downloads a zip of the PDFs.')
                    ->action(fn (): BinaryFileResponse => $this->downloadClientInvoices()),
                Action::make('generateUmbrellaInvoices')
                    ->label('Generate Umbrella Invoices')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Creates one self-bill invoice per umbrella company with approved umbrella-paid days this period and downloads a zip of the PDFs.')
                    ->action(fn (): BinaryFileResponse => $this->downloadUmbrellaInvoices()),
                FactoringScheduleCsvAction::header(
                    fn () => $this->periodCompany(),
                    fn () => $this->currentPeriod(),
                ),
                PayeExportCsvAction::header(
                    fn () => $this->approvedDayPeriodsQuery()->get(),
                    fn () => $this->currentPeriod(),
                ),
                HmrcIntermediariesReportCsvAction::header(
                    fn () => $this->approvedDayPeriodsQuery()->get(),
                    fn () => $this->currentPeriod(),
                ),
            ])
            ->defaultSort('date')
            ->paginated(false)
            ->emptyStateHeading('No approved days for this period yet');
    }

    private function downloadClientInvoices(): BinaryFileResponse
    {
        $period = $this->currentPeriod();
        $results = GenerateClientInvoices::run($this->periodCompany(), $period['start'], $period['end']);

        Notification::make()
            ->title($results->count().' client invoice(s) generated')
            ->success()
            ->send();

        return $this->zipPdfs($results, 'client-invoices', $period);
    }

    private function downloadUmbrellaInvoices(): BinaryFileResponse
    {
        $period = $this->currentPeriod();
        $results = GenerateUmbrellaInvoices::run($this->periodCompany(), $period['start'], $period['end']);

        Notification::make()
            ->title($results->count().' umbrella invoice(s) generated')
            ->success()
            ->send();

        return $this->zipPdfs($results, 'umbrella-invoices', $period);
    }

    /** @param  Collection<int, array{invoice: Invoice, pdf: string}>  $results */
    private function zipPdfs($results, string $prefix, array $period): BinaryFileResponse
    {
        $zipPath = tempnam(sys_get_temp_dir(), $prefix.'-');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        foreach ($results as $result) {
            $zip->addFromString($result['invoice']->number.'.pdf', $result['pdf']);
        }

        $zip->close();

        $filename = "{$prefix}-{$period['start']->toDateString()}-to-{$period['end']->toDateString()}.zip";

        return response()->download($zipPath, $filename)->deleteFileAfterSend();
    }

    private function approvedDayPeriodsQuery()
    {
        $period = $this->currentPeriod();

        return BookingDay::query()
            ->whereHas('booking', fn ($query) => $query->visibleToCurrentUser()->excludingRequests())
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->whereNull('cancelled_at')
            ->whereNotNull('approved_at')
            ->with([
                'booking.client' => fn ($query) => $query->withTrashed(),
                'booking.candidate' => fn ($query) => $query->withTrashed(),
                'booking.jobTitle',
            ]);
    }

    protected function periodCompany(): Company
    {
        return Auth::user()->company;
    }

    private function clientLabel(BookingDay $record): string
    {
        $client = $record->booking?->client;

        if (! $client) {
            return 'Unknown client';
        }

        return $client->trashed() ? "{$client->name} (deleted)" : $client->name;
    }

    private function candidateLabel(BookingDay $record): string
    {
        $candidate = $record->booking?->candidate;

        if (! $candidate) {
            return 'Unknown candidate';
        }

        $name = trim("{$candidate->first_name} {$candidate->last_name}");

        return $candidate->trashed() ? "{$name} (deleted)" : $name;
    }
}
