<?php

namespace App\Console\Commands;

use App\Enums\Integration;
use App\Models\EducationCandidate;
use App\Models\IntegrationSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reconciles Evertime's own candidate records (exported to CSV, since the
 * API has no "list all" endpoint — only a lookup by a known CandidateId)
 * against education_candidates, so historical Evertime candidates that
 * predate this CRM get the same provider_external_id link that candidates
 * pushed by this app already receive automatically. Matches by email first
 * (unique per company here), falling back to NI number. Never overwrites an
 * existing mapping — a mismatch between what's already stored and what the
 * CSV suggests is reported as a conflict for manual review instead.
 */
#[Signature('evertime:map-candidates {path : Path to Evertime\'s "Candidate Export.csv"} {--company= : Company ID to scope candidates to (auto-detected from Evertime integration settings if omitted)} {--commit : Actually store the resolved external IDs — default is a dry run that only reports what it would do}')]
#[Description('Match every row in Evertime\'s candidate export CSV to an education_candidates row by email/NI number and store the resolved Evertime CandidateId as its provider_external_id')]
class MapEvertimeCandidateIds extends Command
{
    private bool $commit = false;

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');

        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            return self::FAILURE;
        }

        $rows = $this->readCsv($path);

        if ($rows === null) {
            $this->error("Could not read/parse: {$path}");

            return self::FAILURE;
        }

        [$groups, $noKeyRows] = $this->groupRows($rows);

        $report = [
            'mapped' => [],
            'already_mapped' => 0,
            'conflict' => [],
            'ambiguous' => [],
            'no_crm_match' => [],
            'multiple_crm_match' => [],
        ];

        foreach ($groups as $key => $group) {
            $this->processGroup($key, $group, $companyId, $report);
        }

        $this->printSummary($report, count($groups), count($noKeyRows));
        $this->writeReportFile($report, $noKeyRows);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    private function processGroup(string $key, array $group, int $companyId, array &$report): void
    {
        [$type, $value] = explode(':', $key, 2);

        $match = $this->findCrmCandidate(
            $companyId,
            $type === 'email' ? $value : null,
            $type === 'ni' ? $value : null,
        );

        if ($match['status'] === 'none') {
            $report['no_crm_match'][] = $this->describeGroup($group);

            return;
        }

        if ($match['status'] === 'multiple') {
            $report['multiple_crm_match'][] = $this->describeGroup($group);

            return;
        }

        $candidate = $match['candidate'];
        $existingId = $candidate->providerExternalId(Integration::Evertime);
        $distinctIds = collect($group)->map(fn (array $row): string => trim($row['CandidateId']))->unique();

        // Already having a stored ID that's one of this person's several
        // Evertime CandidateIds resolves the "which one" question outright —
        // there's nothing ambiguous about it in practice, so this check runs
        // before resolveChosenRow() even for a multi-CandidateId group.
        if ($existingId !== null && $distinctIds->contains($existingId)) {
            $report['already_mapped']++;

            return;
        }

        $chosenRow = $this->resolveChosenRow($group, $candidate->payment_method?->value);

        if ($chosenRow === null) {
            $report['ambiguous'][] = $this->describeGroup($group)." — candidate #{$candidate->id} ({$candidate->email})".($existingId !== null ? " — existing=\"{$existingId}\" (not among CSV ids)" : '');

            return;
        }

        $newId = trim($chosenRow['CandidateId']);

        if ($existingId !== null) {
            $report['conflict'][] = "Candidate #{$candidate->id} {$candidate->first_name} {$candidate->last_name} ({$candidate->email}): existing=\"{$existingId}\" csv=\"{$newId}\"";

            return;
        }

        $report['mapped'][] = "Candidate #{$candidate->id} {$candidate->first_name} {$candidate->last_name} ({$candidate->email}) -> {$newId}";

        if ($this->commit) {
            $candidate->setProviderExternalId(Integration::Evertime, $newId);
        }
    }

    /**
     * @param  array<int, array<string, string>>  $group
     * @return array{status: string, candidate: ?EducationCandidate}
     */
    private function findCrmCandidate(int $companyId, ?string $email, ?string $ni): array
    {
        $matches = collect();

        if ($email !== null) {
            $matches = EducationCandidate::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->get();
        }

        if ($matches->isEmpty() && $ni !== null) {
            $matches = EducationCandidate::query()
                ->where('company_id', $companyId)
                ->whereRaw('UPPER(REPLACE(ni_number, " ", "")) = ?', [$ni])
                ->get();
        }

        return match (true) {
            $matches->count() === 1 => ['status' => 'found', 'candidate' => $matches->first()],
            $matches->count() === 0 => ['status' => 'none', 'candidate' => null],
            default => ['status' => 'multiple', 'candidate' => null],
        };
    }

    /**
     * A person can have several Evertime CandidateId records (typically one
     * per payment type — PAYE vs Umbrella Company — plus the odd genuine
     * duplicate). Resolves to a single row when possible: unambiguous as-is,
     * or after narrowing to Status=Open rows, or as a last resort by
     * matching the CRM candidate's own payment_method. Returns null rather
     * than guessing when it's still ambiguous.
     *
     * @param  array<int, array<string, string>>  $group
     * @return array<string, string>|null
     */
    private function resolveChosenRow(array $group, ?string $paymentMethod): ?array
    {
        if (collect($group)->pluck('CandidateId')->unique()->count() === 1) {
            return $group[0];
        }

        $openRows = array_values(array_filter($group, fn (array $row): bool => strcasecmp(trim($row['Status']), 'Open') === 0));
        $pool = $openRows !== [] ? $openRows : $group;

        if (collect($pool)->pluck('CandidateId')->unique()->count() === 1) {
            return $pool[0];
        }

        $wantedType = match ($paymentMethod) {
            'paye' => 'PAYE',
            'umbrella' => 'Umbrella Company',
            default => null,
        };

        if ($wantedType !== null) {
            $typeMatches = array_values(array_filter($pool, fn (array $row): bool => strcasecmp(trim($row['CandidateType']), $wantedType) === 0));

            if (count($typeMatches) === 1) {
                return $typeMatches[0];
            }
        }

        return null;
    }

    /** @param array<int, array<string, string>> $group */
    private function describeGroup(array $group): string
    {
        $row = $group[0];
        $ids = collect($group)->pluck('CandidateId')->unique()->implode(', ');
        $name = trim($row['Surname'].', '.$row['Forenames']);

        return "{$name} ({$row['Email']}) — CandidateId(s): {$ids}";
    }

    private function resolveCompanyId(): ?int
    {
        if ($this->option('company')) {
            return (int) $this->option('company');
        }

        $companyIds = IntegrationSetting::query()
            ->where('provider', Integration::Evertime->value)
            ->distinct()
            ->pluck('company_id');

        if ($companyIds->count() === 1) {
            return $companyIds->first();
        }

        if ($companyIds->isEmpty()) {
            $this->error('No company has Evertime configured. Pass --company=<id> explicitly.');
        } else {
            $this->error('Multiple companies have Evertime configured ('.$companyIds->implode(', ').'). Pass --company=<id> to pick one.');
        }

        return null;
    }

    /** @return array<int, array<string, string>>|null */
    private function readCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return null;
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($header)) {
                continue;
            }

            $rows[] = array_combine($header, $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{0: array<string, array<int, array<string, string>>>, 1: array<int, array<string, string>>}
     */
    private function groupRows(array $rows): array
    {
        $groups = [];
        $noKeyRows = [];

        foreach ($rows as $row) {
            $email = strtolower(trim($row['Email'] ?? ''));
            $ni = strtoupper(str_replace(' ', '', trim($row['NiNumber'] ?? '')));

            $key = $email !== '' ? "email:{$email}" : ($ni !== '' ? "ni:{$ni}" : null);

            if ($key === null) {
                $noKeyRows[] = $row;

                continue;
            }

            $groups[$key][] = $row;
        }

        return [$groups, $noKeyRows];
    }

    /** @param array<string, mixed> $report */
    private function printSummary(array $report, int $groupCount, int $noKeyCount): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Mode', $this->commit ? 'LIVE — external IDs written' : 'DRY RUN (no writes)');
        $this->components->twoColumnDetail('Evertime candidate groups (deduped by email/NI)', (string) $groupCount);
        $this->components->twoColumnDetail($this->commit ? 'Newly mapped' : 'Would map', (string) count($report['mapped']));
        $this->components->twoColumnDetail('Already correctly mapped', (string) $report['already_mapped']);
        $this->components->twoColumnDetail('Conflicts (existing mapping differs — left untouched)', (string) count($report['conflict']));
        $this->components->twoColumnDetail('Ambiguous (multiple CandidateIds, could not resolve)', (string) count($report['ambiguous']));
        $this->components->twoColumnDetail('No matching CRM candidate', (string) count($report['no_crm_match']));
        $this->components->twoColumnDetail('Multiple CRM candidates matched', (string) count($report['multiple_crm_match']));
        $this->components->twoColumnDetail('CSV rows with neither email nor NI (skipped)', (string) $noKeyCount);
        $this->newLine();

        foreach (['conflict' => 'Conflicts', 'ambiguous' => 'Ambiguous', 'multiple_crm_match' => 'Multiple CRM matches'] as $reportKey => $label) {
            if ($report[$reportKey] === []) {
                continue;
            }

            $this->warn("{$label}:");

            foreach (array_slice($report[$reportKey], 0, 25) as $line) {
                $this->line("  - {$line}");
            }

            if (count($report[$reportKey]) > 25) {
                $this->line('  ... and '.(count($report[$reportKey]) - 25).' more (see report file)');
            }

            $this->newLine();
        }

        $this->info('Full detail, including every mapped/would-map row, is in the report file below.');
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<int, array<string, string>>  $noKeyRows
     */
    private function writeReportFile(array $report, array $noKeyRows): void
    {
        $lines = [
            'Mode: '.($this->commit ? 'LIVE' : 'DRY RUN'),
            'Generated: '.now()->toDateTimeString(),
            '',
            '== Mapped ('.count($report['mapped']).') ==',
            ...$report['mapped'],
            '',
            '== Conflicts ('.count($report['conflict']).') ==',
            ...$report['conflict'],
            '',
            '== Ambiguous ('.count($report['ambiguous']).') ==',
            ...$report['ambiguous'],
            '',
            '== No matching CRM candidate ('.count($report['no_crm_match']).') ==',
            ...$report['no_crm_match'],
            '',
            '== Multiple CRM candidates matched ('.count($report['multiple_crm_match']).') ==',
            ...$report['multiple_crm_match'],
            '',
            '== CSV rows with neither email nor NI ('.count($noKeyRows).') ==',
            ...collect($noKeyRows)->map(fn (array $row): string => trim($row['Surname'].', '.$row['Forenames'])." — CandidateId: {$row['CandidateId']}"),
        ];

        $path = storage_path('app/evertime-candidate-mapping-'.now()->format('Y-m-d-His').'.txt');
        file_put_contents($path, implode(PHP_EOL, $lines));

        $this->line("Report written to: {$path}");
    }
}
