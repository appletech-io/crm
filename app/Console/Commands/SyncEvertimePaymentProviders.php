<?php

namespace App\Console\Commands;

use App\Enums\Integration;
use App\Enums\PaymentMethod;
use App\Models\EducationCandidate;
use App\Models\IntegrationSetting;
use App\Models\PaymentProvider;
use App\Models\ProviderExternalId;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Sets each candidate's real payment_method/payment_provider_id from
 * Evertime's own records, keyed off the Evertime CandidateId already stored
 * on the candidate (see evertime:map-candidates) — not by re-matching on
 * name/email, which Evertime's own duplicate umbrella-company records make
 * unreliable. A candidate's exact CandidateId row in the CSV names exactly
 * one CompanyId, so there's nothing to disambiguate here: PAYE means
 * payment_provider_id stays null (SendClientBookingConfirmationEmail/
 * EvertimeProvider's own push logic already treats a null provider as PAYE —
 * see EvertimeProvider::upsertCandidate()), otherwise a PaymentProvider row
 * for that exact CompanyId is looked up or created and attached.
 *
 * A CompanyId named in --reuse attaches to an already-existing PaymentProvider
 * instead of creating a new one — used for the one CompanyId per pre-existing
 * CRM provider (Orbital/Generate/Azebra/People Group Services) that should
 * keep using that record rather than getting its own duplicate; every other
 * CompanyId under the same Evertime company name still gets its own new row,
 * since Evertime itself treats them as distinct company records.
 */
#[Signature('evertime:sync-payment-providers {candidates : Path to Evertime\'s "Candidate Export.csv"} {companies : Path to Evertime\'s "Company Export.csv"} {--company= : Company ID to scope candidates to (auto-detected from Evertime integration settings if omitted)} {--reuse=* : Attach an Evertime CompanyId to an existing PaymentProvider instead of creating a new one, as companyId:providerId (repeatable, or comma-separated)} {--commit : Actually write the changes — default is a dry run that only reports what it would do}')]
#[Description('Set payment_method/payment_provider_id on every education_candidates row that already has an Evertime CandidateId, from that exact row in the candidate export CSV — creating a PaymentProvider per distinct Evertime CompanyId as needed, or reusing an existing one per --reuse')]
class SyncEvertimePaymentProviders extends Command
{
    private bool $commit = false;

    private int $companyIdForNewProviders = 0;

    /** @var array<string, int> Evertime CompanyId => resolved PaymentProvider id, cached for this run */
    private array $resolvedProviderIds = [];

    /** @var array<string, int> Evertime CompanyId => existing PaymentProvider id to reuse, from --reuse */
    private array $reuseMap = [];

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');

        $candidatesPath = (string) $this->argument('candidates');
        $companiesPath = (string) $this->argument('companies');

        if (! is_file($candidatesPath)) {
            $this->error("File not found: {$candidatesPath}");

            return self::FAILURE;
        }

        if (! is_file($companiesPath)) {
            $this->error("File not found: {$companiesPath}");

            return self::FAILURE;
        }

        if (! $this->parseReuseOption()) {
            return self::FAILURE;
        }

        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            return self::FAILURE;
        }

        $candidateRows = $this->readCsv($candidatesPath);
        $companyRows = $this->readCsv($companiesPath);

        if ($candidateRows === null || $companyRows === null) {
            $this->error('Could not read/parse one of the CSV files.');

            return self::FAILURE;
        }

        $candidatesByEvertimeId = collect($candidateRows)->keyBy(fn (array $row): string => trim($row['CandidateId']));
        $companiesByEvertimeId = collect($companyRows)->keyBy(fn (array $row): string => trim($row['CompanyId']));

        $report = [
            'no_csv_row' => [],
            'unknown_candidate_type' => [],
            'unchanged' => 0,
            'changed' => [],
            'providers_created' => [],
            'providers_reused' => [],
        ];

        $externalIds = ProviderExternalId::query()
            ->where('provider', Integration::Evertime->value)
            ->where('model_type', EducationCandidate::class)
            ->pluck('external_id', 'model_id');

        $candidates = EducationCandidate::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $externalIds->keys())
            ->get();

        foreach ($candidates as $candidate) {
            $evertimeId = $externalIds[$candidate->id];
            $row = $candidatesByEvertimeId->get($evertimeId);

            if ($row === null) {
                $report['no_csv_row'][] = "Candidate #{$candidate->id} {$candidate->first_name} {$candidate->last_name} — CandidateId \"{$evertimeId}\" not in CSV";

                continue;
            }

            $this->syncCandidate($candidate, $row, $companiesByEvertimeId, $report);
        }

        $this->printSummary($report, $candidates->count());
        $this->writeReportFile($report);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, array<string, string>>  $companiesByEvertimeId
     * @param  array<string, mixed>  $report
     */
    private function syncCandidate(EducationCandidate $candidate, array $row, $companiesByEvertimeId, array &$report): void
    {
        $candidateType = trim($row['CandidateType']);

        if ($candidateType === 'PAYE') {
            $targetMethod = PaymentMethod::Paye;
            $targetProviderId = null;
        } elseif ($candidateType === 'Umbrella Company') {
            $targetMethod = PaymentMethod::Umbrella;
            $targetProviderId = $this->resolveProviderId(trim($row['CompanyId']), $companiesByEvertimeId, $report);
        } else {
            $report['unknown_candidate_type'][] = "Candidate #{$candidate->id} {$candidate->first_name} {$candidate->last_name} — unrecognised CandidateType \"{$candidateType}\"";

            return;
        }

        $unchanged = $candidate->payment_method === $targetMethod && $candidate->payment_provider_id === $targetProviderId;

        if ($unchanged) {
            $report['unchanged']++;

            return;
        }

        $report['changed'][] = sprintf(
            'Candidate #%d %s %s: payment_method %s -> %s, payment_provider_id %s -> %s',
            $candidate->id,
            $candidate->first_name,
            $candidate->last_name,
            $candidate->payment_method?->value ?? 'null',
            $targetMethod->value,
            $candidate->payment_provider_id ?? 'null',
            $targetProviderId ?? 'null',
        );

        if ($this->commit) {
            $candidate->update([
                'payment_method' => $targetMethod,
                'payment_provider_id' => $targetProviderId,
            ]);
        }
    }

    /**
     * @param  Collection<string, array<string, string>>  $companiesByEvertimeId
     * @param  array<string, mixed>  $report
     */
    private function resolveProviderId(string $evertimeCompanyId, $companiesByEvertimeId, array &$report): int
    {
        if (isset($this->resolvedProviderIds[$evertimeCompanyId])) {
            return $this->resolvedProviderIds[$evertimeCompanyId];
        }

        $existingId = ProviderExternalId::query()
            ->where('provider', Integration::Evertime->value)
            ->where('model_type', PaymentProvider::class)
            ->where('external_id', $evertimeCompanyId)
            ->value('model_id');

        if ($existingId !== null) {
            $this->resolvedProviderIds[$evertimeCompanyId] = $existingId;

            return $existingId;
        }

        $companyRow = $companiesByEvertimeId->get($evertimeCompanyId);
        $name = $companyRow ? $this->cleanUpName($companyRow['CompanyName']) : "Evertime company {$evertimeCompanyId}";

        if (isset($this->reuseMap[$evertimeCompanyId])) {
            $reuseProviderId = $this->reuseMap[$evertimeCompanyId];
            $report['providers_reused'][] = "\"{$name}\" (Evertime CompanyId {$evertimeCompanyId}) -> existing PaymentProvider #{$reuseProviderId}";

            if ($this->commit) {
                $provider = PaymentProvider::findOrFail($reuseProviderId);
                $provider->setProviderExternalId(Integration::Evertime, $evertimeCompanyId);
            }

            $this->resolvedProviderIds[$evertimeCompanyId] = $reuseProviderId;

            return $reuseProviderId;
        }

        $report['providers_created'][] = "\"{$name}\" (Evertime CompanyId {$evertimeCompanyId})";

        if (! $this->commit) {
            // No real id yet in dry-run mode — a negative placeholder keyed
            // by CompanyId keeps every candidate in this run consistently
            // "assigned" to the same not-yet-created provider for reporting,
            // without colliding with any other CompanyId's placeholder.
            $placeholder = -1 * (crc32($evertimeCompanyId) % 1000000 + 1);
            $this->resolvedProviderIds[$evertimeCompanyId] = $placeholder;

            return $placeholder;
        }

        $provider = PaymentProvider::create([
            'company_id' => $this->companyIdForNewProviders,
            'name' => $name,
            'address_1' => $companyRow['AddressLine1'] ?? null,
            'address_2' => $companyRow['AddressLine2'] ?? null,
            'county' => $companyRow['County'] ?? null,
            'postcode' => $companyRow['Postcode'] ?? null,
        ]);

        $provider->setProviderExternalId(Integration::Evertime, $evertimeCompanyId);

        $this->resolvedProviderIds[$evertimeCompanyId] = $provider->id;

        return $provider->id;
    }

    private function cleanUpName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    private function parseReuseOption(): bool
    {
        $pairs = collect($this->option('reuse'))
            ->flatMap(fn (string $value): array => explode(',', $value))
            ->map(fn (string $value): string => trim($value))
            ->filter();

        foreach ($pairs as $pair) {
            if (! str_contains($pair, ':')) {
                $this->error("Invalid --reuse value \"{$pair}\" — expected companyId:providerId.");

                return false;
            }

            [$companyId, $providerId] = explode(':', $pair, 2);
            $this->reuseMap[trim($companyId)] = (int) trim($providerId);
        }

        return true;
    }

    private function resolveCompanyId(): ?int
    {
        if ($this->option('company')) {
            return $this->companyIdForNewProviders = (int) $this->option('company');
        }

        $companyIds = IntegrationSetting::query()
            ->where('provider', Integration::Evertime->value)
            ->distinct()
            ->pluck('company_id');

        if ($companyIds->count() === 1) {
            return $this->companyIdForNewProviders = $companyIds->first();
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

    /** @param array<string, mixed> $report */
    private function printSummary(array $report, int $totalWithEvertimeId): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Mode', $this->commit ? 'LIVE — changes written' : 'DRY RUN (no writes)');
        $this->components->twoColumnDetail('Candidates with an Evertime CandidateId on file', (string) $totalWithEvertimeId);
        $this->components->twoColumnDetail('CandidateId not found in CSV (skipped)', (string) count($report['no_csv_row']));
        $this->components->twoColumnDetail('Unrecognised CandidateType (skipped)', (string) count($report['unknown_candidate_type']));
        $this->components->twoColumnDetail('Already correct', (string) $report['unchanged']);
        $this->components->twoColumnDetail($this->commit ? 'Changed' : 'Would change', (string) count($report['changed']));
        $this->components->twoColumnDetail($this->commit ? 'New payment providers created' : 'New payment providers that would be created', (string) count(array_unique($report['providers_created'])));
        $this->components->twoColumnDetail($this->commit ? 'Attached to an existing provider' : 'Would attach to an existing provider', (string) count(array_unique($report['providers_reused'])));
        $this->newLine();

        if ($report['providers_reused'] !== []) {
            $this->line('Reusing an existing payment provider:');

            foreach (array_unique($report['providers_reused']) as $line) {
                $this->line("  - {$line}");
            }

            $this->newLine();
        }

        if ($report['providers_created'] !== []) {
            $this->line('New payment providers (one per distinct Evertime CompanyId):');

            foreach (array_unique($report['providers_created']) as $line) {
                $this->line("  - {$line}");
            }

            $this->newLine();
        }

        $this->info('Full detail, including every changed candidate, is in the report file below.');
    }

    /** @param array<string, mixed> $report */
    private function writeReportFile(array $report): void
    {
        $lines = [
            'Mode: '.($this->commit ? 'LIVE' : 'DRY RUN'),
            'Generated: '.now()->toDateTimeString(),
            '',
            '== New payment providers ('.count(array_unique($report['providers_created'])).') ==',
            ...array_unique($report['providers_created']),
            '',
            '== Reused existing payment providers ('.count(array_unique($report['providers_reused'])).') ==',
            ...array_unique($report['providers_reused']),
            '',
            '== Changed ('.count($report['changed']).') ==',
            ...$report['changed'],
            '',
            '== CandidateId not found in CSV ('.count($report['no_csv_row']).') ==',
            ...$report['no_csv_row'],
            '',
            '== Unrecognised CandidateType ('.count($report['unknown_candidate_type']).') ==',
            ...$report['unknown_candidate_type'],
        ];

        $path = storage_path('app/evertime-payment-provider-sync-'.now()->format('Y-m-d-His').'.txt');
        file_put_contents($path, implode(PHP_EOL, $lines));

        $this->line("Report written to: {$path}");
    }
}
