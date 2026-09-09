<?php

namespace App\Filament\Support;

use App\Models\BookingDay;
use App\Models\Client;
use App\Models\Company;
use App\Services\Payroll\ClientTimesheetData;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Builds the "Download Timesheets" header action on the Run Payroll page —
 * a ZIP of one PDF per client for the period, each listing every contractor
 * (candidate) booked there that week with their days, job title, and charge
 * rate, plus the approver's name/timestamp once every day for that
 * contractor has actually been approved (see ClientTimesheetData). Mirrors
 * the layout of a real external client timesheet, built entirely from this
 * app's own data.
 */
class ExportPayrollTimesheetsZipAction
{
    /**
     * @param  Closure(): Collection<int, BookingDay>  $dayPeriods
     * @param  Closure(): array{start: Carbon, end: Carbon}  $period
     * @param  Closure(): Company  $company
     */
    public static function header(Closure $dayPeriods, Closure $period, Closure $company): Action
    {
        return Action::make('exportPayrollTimesheetsZip')
            ->label('Download Timesheets')
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->color('gray')
            ->action(fn (): BinaryFileResponse => static::download($dayPeriods(), $period(), $company()));
    }

    /**
     * Public so it's directly testable without going through a Filament
     * Action/Livewire context, same as ExportPayrollCsvAction::download().
     *
     * @param  Collection<int, BookingDay>  $dayPeriods
     * @param  array{start: Carbon, end: Carbon}  $period
     */
    public static function download(Collection $dayPeriods, array $period, Company $company): BinaryFileResponse
    {
        // tempnam() already creates an empty file at this path — OVERWRITE
        // (rather than CREATE) is what lets ZipArchive open and truncate it,
        // since CREATE alone errors when the target already exists.
        $zipPath = tempnam(sys_get_temp_dir(), 'payroll-timesheets-');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        static::byClient($dayPeriods)
            ->sortBy(fn (Collection $days): string => $days->first()->booking->client->name)
            ->each(function (Collection $days) use ($zip, $period, $company): void {
                $client = $days->first()->booking->client;

                $zip->addFromString(
                    static::filenameFor($client),
                    static::renderClientTimesheet($client, $days, $period, $company)
                );
            });

        $zip->close();

        $filename = 'payroll-timesheets-'.$period['start']->toDateString().'-to-'.$period['end']->toDateString().'.zip';

        return response()->download($zipPath, $filename)->deleteFileAfterSend();
    }

    /**
     * @param  Collection<int, BookingDay>  $dayPeriods
     * @return Collection<int, Collection<int, BookingDay>> keyed by client_id
     */
    private static function byClient(Collection $dayPeriods): Collection
    {
        return $dayPeriods->groupBy(fn (BookingDay $day) => $day->booking->client_id);
    }

    private static function filenameFor(Client $client): string
    {
        return Str::slug($client->name).'-'.$client->id.'.pdf';
    }

    /**
     * @param  Collection<int, BookingDay>  $days
     * @param  array{start: Carbon, end: Carbon}  $period
     */
    private static function renderClientTimesheet(Client $client, Collection $days, array $period, Company $company): string
    {
        $html = view('pdfs.payroll-client-timesheet', [
            'client' => $client,
            'period' => $period,
            'contractors' => ClientTimesheetData::contractorsFor($days),
            'logoDataUri' => static::logoDataUri($company),
        ])->render();

        return Pdf::loadHTML($html)->output();
    }

    private static function logoDataUri(Company $company): string
    {
        return "data:{$company->logoMimeType()};base64,".base64_encode($company->logoContents());
    }
}
