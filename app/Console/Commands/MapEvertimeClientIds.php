<?php

namespace App\Console\Commands;

use App\Enums\Integration;
use App\Models\Client;
use App\Models\IntegrationSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Matches Evertime's "Client Export.csv" (one row per client/location/contact
 * combination, keyed by "Client Display ID") to clients, and stores the
 * resolved Display ID as a provider_external_id — the same reconciliation
 * evertime:map-candidates does for candidates, but clients have no email/NI
 * to key off, so matching is by normalized name and/or postcode instead.
 * Never overwrites an existing mapping that disagrees with the CSV; that's
 * reported as a conflict for manual review instead.
 */
#[Signature('evertime:map-clients {path : Path to Evertime\'s "Client Export.csv"} {--company= : Company ID to scope clients to (auto-detected from Evertime integration settings if omitted)} {--commit : Actually store the resolved external IDs — default is a dry run that only reports what it would do}')]
#[Description('Match every client in Evertime\'s client export CSV to a clients row by normalized name/postcode and store the resolved Client Display ID as its provider_external_id')]
class MapEvertimeClientIds extends Command
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

        $evertimeClients = $this->groupByDisplayId($rows);

        $clients = Client::query()->where('company_id', $companyId)->get();
        $byNameAndPostcode = $clients->groupBy(fn (Client $c): string => $this->normalizeName($c->name).'|'.$this->normalizePostcode($c->postcode));
        $byPostcode = $clients->groupBy(fn (Client $c): string => $this->normalizePostcode($c->postcode))->forget('');
        $byName = $clients->groupBy(fn (Client $c): string => $this->normalizeName($c->name));

        $report = [
            'mapped' => [],
            'already_mapped' => 0,
            'conflict' => [],
            'ambiguous' => [],
            'no_crm_match' => [],
        ];

        /** @var array<int, array<int, array{displayId: string, status: string}>> $matchedToClient */
        $matchedToClient = [];

        foreach ($evertimeClients as $displayId => $evertimeClient) {
            $client = $this->findMatch($displayId, $evertimeClient, $byNameAndPostcode, $byPostcode, $byName, $report);

            if ($client) {
                $matchedToClient[$client->id][] = ['displayId' => $displayId, 'status' => $evertimeClient['Status']];
            }
        }

        $clientsById = $clients->keyBy('id');

        foreach ($matchedToClient as $clientId => $matches) {
            $this->resolveClient($clientsById[$clientId], $matches, $report);
        }

        $this->printSummary($report, count($evertimeClients));
        $this->writeReportFile($report);

        return self::SUCCESS;
    }

    /**
     * Finds the single CRM client an Evertime client row matches, trying
     * name+postcode, then postcode alone, then name alone. Immediately
     * reports (and returns null for) the "no match" and "matched more than
     * one CRM client" cases — those don't depend on what else in the CSV
     * matches the same client, unlike the "which of several Evertime records
     * is this client's real one" question, which resolveClient() answers
     * once every Evertime row has been matched.
     *
     * @param  array<string, string>  $evertimeClient
     * @param  Collection<string, Collection<int, Client>>  $byNameAndPostcode
     * @param  Collection<string, Collection<int, Client>>  $byPostcode
     * @param  Collection<string, Collection<int, Client>>  $byName
     * @param  array<string, mixed>  $report
     */
    private function findMatch(string $displayId, array $evertimeClient, $byNameAndPostcode, $byPostcode, $byName, array &$report): ?Client
    {
        $name = $this->normalizeName($evertimeClient['Name']);
        $postcode = $this->normalizePostcode($evertimeClient['Postal Code']);

        $matches = $byNameAndPostcode->get("{$name}|{$postcode}", collect());
        $tier = 'name+postcode';

        if ($matches->isEmpty() && $postcode !== '') {
            $matches = $byPostcode->get($postcode, collect());
            $tier = 'postcode';
        }

        if ($matches->isEmpty()) {
            $matches = $byName->get($name, collect());
            $tier = 'name';
        }

        $description = "{$evertimeClient['Name']} ({$evertimeClient['Postal Code']}) — Display ID {$displayId}";

        if ($matches->isEmpty()) {
            $report['no_crm_match'][] = $description;

            return null;
        }

        if ($matches->count() > 1) {
            $names = $matches->map(fn (Client $c): string => "#{$c->id} {$c->name}")->implode(', ');
            $report['ambiguous'][] = "{$description} — matched {$matches->count()} clients via {$tier}: {$names}";

            return null;
        }

        return $matches->first();
    }

    /**
     * A client can have several Evertime records (renames, re-imports, the
     * odd genuine duplicate) — a client already mapped just needs that
     * mapping confirmed against whichever of them it matches; a client with
     * no mapping yet needs exactly one Evertime record to adopt, falling
     * back to preferring an Open one when more than one candidate exists,
     * and giving up as ambiguous rather than guessing when that still ties.
     *
     * @param  array<int, array{displayId: string, status: string}>  $matches
     * @param  array<string, mixed>  $report
     */
    private function resolveClient(Client $client, array $matches, array &$report): void
    {
        $distinctIds = collect($matches)->pluck('displayId')->unique();
        $existingId = $client->providerExternalId(Integration::Evertime);

        if ($existingId !== null) {
            if ($distinctIds->contains($existingId)) {
                $report['already_mapped']++;

                return;
            }

            $report['conflict'][] = "Client #{$client->id} {$client->name}: existing=\"{$existingId}\" csv=".$distinctIds->map(fn (string $id): string => "\"{$id}\"")->implode(', ');

            return;
        }

        if ($distinctIds->count() > 1) {
            $openIds = collect($matches)
                ->filter(fn (array $m): bool => strcasecmp(trim($m['status']), 'Open') === 0)
                ->pluck('displayId')
                ->unique();

            if ($openIds->count() === 1) {
                $distinctIds = $openIds;
            } else {
                $report['ambiguous'][] = "Client #{$client->id} {$client->name} — matched several Evertime records with no existing mapping to disambiguate: ".$distinctIds->implode(', ');

                return;
            }
        }

        $displayId = $distinctIds->first();

        $report['mapped'][] = "Client #{$client->id} {$client->name} -> {$displayId}";

        if ($this->commit) {
            $client->setProviderExternalId(Integration::Evertime, $displayId);
        }
    }

    private function normalizeName(string $name): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }

    private function normalizePostcode(?string $postcode): string
    {
        return strtoupper(str_replace(' ', '', trim((string) $postcode)));
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<string, array<string, string>>
     */
    private function groupByDisplayId(array $rows): array
    {
        $clients = [];

        foreach ($rows as $row) {
            $id = trim($row['Client Display ID']);

            if ($id === '') {
                continue;
            }

            if (! isset($clients[$id])) {
                $clients[$id] = ['Name' => '', 'Postal Code' => '', 'Status' => ''];
            }

            if ($clients[$id]['Name'] === '' && trim($row['Name']) !== '') {
                $clients[$id]['Name'] = trim(preg_replace('/\s+/', ' ', $row['Name']) ?? $row['Name']);
            }

            if ($clients[$id]['Postal Code'] === '' && trim($row['Postal Code']) !== '') {
                $clients[$id]['Postal Code'] = trim($row['Postal Code']);
            }

            if ($clients[$id]['Status'] === '' && trim($row['Status']) !== '') {
                $clients[$id]['Status'] = trim($row['Status']);
            }
        }

        return $clients;
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

    /** @param array<string, mixed> $report */
    private function printSummary(array $report, int $totalEvertimeClients): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Mode', $this->commit ? 'LIVE — external IDs written' : 'DRY RUN (no writes)');
        $this->components->twoColumnDetail('Evertime clients (deduped by Display ID)', (string) $totalEvertimeClients);
        $this->components->twoColumnDetail($this->commit ? 'Newly mapped' : 'Would map', (string) count($report['mapped']));
        $this->components->twoColumnDetail('Already correctly mapped', (string) $report['already_mapped']);
        $this->components->twoColumnDetail('Conflicts (existing mapping differs — left untouched)', (string) count($report['conflict']));
        $this->components->twoColumnDetail('Ambiguous (matched more than one CRM client)', (string) count($report['ambiguous']));
        $this->components->twoColumnDetail('No matching CRM client', (string) count($report['no_crm_match']));
        $this->newLine();

        foreach (['conflict' => 'Conflicts', 'ambiguous' => 'Ambiguous'] as $reportKey => $label) {
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

    /** @param array<string, mixed> $report */
    private function writeReportFile(array $report): void
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
            '== No matching CRM client ('.count($report['no_crm_match']).') ==',
            ...$report['no_crm_match'],
        ];

        $path = storage_path('app/evertime-client-mapping-'.now()->format('Y-m-d-His').'.txt');
        file_put_contents($path, implode(PHP_EOL, $lines));

        $this->line("Report written to: {$path}");
    }
}
