<?php

namespace App\Filament\Support;

use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the "export selected candidates to CSV" bulk action shared by the
 * Education/Healthcare candidate tables. Only basic contact details are
 * included — no compliance or vetting data.
 */
class ExportCandidatesCsvAction
{
    public static function bulk(): BulkAction
    {
        return BulkAction::make('exportCsv')
            ->label('Export CSV')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records): StreamedResponse => static::download($records));
    }

    private static function download(Collection $records): StreamedResponse
    {
        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['First Name', 'Last Name', 'Email', 'Phone', 'Address', 'City', 'County', 'Postcode', 'Country']);

            /** @var EducationCandidate|HealthcareCandidate $record */
            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->first_name,
                    $record->last_name,
                    $record->email,
                    $record->phone,
                    $record->address,
                    $record->city,
                    $record->county,
                    $record->postcode,
                    $record->country,
                ]);
            }

            fclose($handle);
        }, 'candidates-'.now()->format('Y-m-d-His').'.csv');
    }
}
