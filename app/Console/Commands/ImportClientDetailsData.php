<?php

namespace App\Console\Commands;

use App\Enums\Education\KeyStage;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientContactJobTitle;
use App\Models\ClientType;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('import:client-details {path=client_details_import.jsonl} {--dry-run : Report what would happen without writing anything to the database}')]
#[Description('Import the legacy client_details_import.jsonl export into clients/client_contacts for Applebough')]
class ImportClientDetailsData extends Command
{
    private const string COMPANY_NAME = 'Applebough';

    private const string INDUSTRY_SLUG = 'education';

    private const string CONSULTANT_EMAIL_DOMAIN = 'applebough.co.uk';

    private const string INITIAL_PASSWORD = 'Applebough26!';

    /**
     * Source desk_consultant value (lowercased) => the real consultant's name to
     * resolve/create. "Syed Shah" clients are reassigned to Ashley Greaves;
     * "Kirsty Two" is the same real person as "Kirsty Greaves", not a second
     * consultant, so both map to the one account.
     *
     * @var array<string, string>
     */
    private const array CONSULTANT_NAME_MAP = [
        'ashley greaves' => 'Ashley Greaves',
        'kirsty greaves' => 'Kirsty Greaves',
        'kirsty two' => 'Kirsty Greaves',
        'syed shah' => 'Ashley Greaves',
        'charles palmer' => 'Charles Palmer',
    ];

    private bool $dryRun = false;

    private int $companyId;

    private int $industryId;

    /** @var array<string, int> resolved consultant name => user id */
    private array $consultantIds = [];

    /** @var array<string, int> resolved client type name => id */
    private array $clientTypeIds = [];

    /** @var array<string, int> resolved contact job title name => id */
    private array $jobTitleIds = [];

    /** @var array<string, int> lowercased email => user id, for contacts already given a login this run */
    private array $loginUserIdsByEmail = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $path = (string) $this->argument('path');
        $path = str_starts_with($path, '/') ? $path : base_path($path);

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $company = Company::query()->where('name', self::COMPANY_NAME)->first();
        $industry = Industry::query()->where('slug', self::INDUSTRY_SLUG)->first();

        if (! $company || ! $industry) {
            $this->error('Could not resolve company "'.self::COMPANY_NAME.'" or the "'.self::INDUSTRY_SLUG.'" industry on this database.');

            return self::FAILURE;
        }

        $this->companyId = $company->id;
        $this->industryId = $industry->id;

        $this->resolveOrCreateConsultants();

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            $this->error("Could not read file: {$path}");

            return self::FAILURE;
        }

        $stats = [
            'clients_created' => 0,
            'clients_updated' => 0,
            'contacts_created' => 0,
            'logins_created' => 0,
            'client_types_created' => 0,
            'job_titles_created' => 0,
            'unresolved_consultants' => [],
        ];

        $this->withProgressBar($lines, function (string $line) use (&$stats): void {
            $record = json_decode($line, true);

            if (! is_array($record)) {
                return;
            }

            $this->importClient($record, $stats);
        });

        $this->newLine(2);

        $this->components->twoColumnDetail('Mode', $this->dryRun ? 'DRY RUN (no writes)' : 'LIVE');
        $this->components->twoColumnDetail('Clients created', (string) $stats['clients_created']);
        $this->components->twoColumnDetail('Clients updated', (string) $stats['clients_updated']);
        $this->components->twoColumnDetail('Contacts created', (string) $stats['contacts_created']);
        $this->components->twoColumnDetail('Logins created', (string) $stats['logins_created']);
        $this->components->twoColumnDetail('Client types created', (string) $stats['client_types_created']);
        $this->components->twoColumnDetail('Contact job titles created', (string) $stats['job_titles_created']);

        if (! empty($stats['unresolved_consultants'])) {
            $this->warn('Unresolved desk_consultant values (left null): '.implode(', ', array_unique($stats['unresolved_consultants'])));
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $stats */
    private function importClient(array $record, array &$stats): void
    {
        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            return;
        }

        $postcode = trim((string) ($record['postcode'] ?? '')) ?: null;

        $attributes = [
            'company_id' => $this->companyId,
            'industry_id' => $this->industryId,
            'name' => $name,
            'address' => trim((string) ($record['address'] ?? '')) ?: null,
            'city' => trim((string) ($record['city'] ?? '')) ?: null,
            'postcode' => $postcode,
            'county' => trim((string) ($record['county'] ?? '')) ?: null,
            'phone' => trim((string) ($record['phone'] ?? '')) ?: null,
            'website' => trim((string) ($record['website'] ?? '')) ?: null,
            'notes' => trim((string) ($record['notes'] ?? '')) ?: null,
            'client_type_id' => $this->resolveClientType((string) ($record['client_type'] ?? ''), $stats),
            'key_stages' => $this->resolveKeyStages((string) ($record['key_stages'] ?? '')),
            'consultant_id' => $this->resolveConsultantId($record['desk_consultant'] ?? null, $stats),
        ];

        $existing = Client::query()
            ->where('company_id', $this->companyId)
            ->where('name', $name)
            ->where('postcode', $postcode)
            ->first();

        if ($this->dryRun) {
            $stats[$existing ? 'clients_updated' : 'clients_created']++;

            return;
        }

        DB::transaction(function () use ($existing, $attributes, $record, &$stats): void {
            if ($existing) {
                $existing->update($attributes);
                $client = $existing;
                $stats['clients_updated']++;
            } else {
                $client = Client::create($attributes);
                $stats['clients_created']++;
            }

            $this->importContacts($client, $record, $stats);
        });
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $stats
     */
    private function importContacts(Client $client, array $record, array &$stats): void
    {
        $contacts = $record['contacts'] ?? [];

        if (empty($contacts) && (filled($record['main_contact_first_name'] ?? null) || filled($record['default_email'] ?? null))) {
            $jobTitle = (string) ($record['main_contact_job_title'] ?? '');

            $contacts = [[
                'name' => trim(($record['main_contact_first_name'] ?? '').' '.($record['main_contact_surname'] ?? '')),
                'job_title' => strcasecmp($jobTitle, 'Select') === 0 ? '' : $jobTitle,
                'email' => $record['default_email'] ?? null,
                'is_primary' => true,
                'is_timesheet' => false,
                'is_invoice' => false,
                'is_booking' => false,
                'can_login' => true,
            ]];
        }

        foreach ($contacts as $contactData) {
            if (is_array($contactData)) {
                $this->importContact($client, $contactData, $stats);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $stats
     */
    private function importContact(Client $client, array $data, array &$stats): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $jobTitleName = trim((string) ($data['job_title'] ?? ''));

        if ($name === '') {
            $name = $jobTitleName !== '' ? $jobTitleName : 'Office Contact';
        }

        $parts = preg_split('/\s+/', $name) ?: [$name];
        $firstName = (string) array_shift($parts);
        $lastName = $parts ? implode(' ', $parts) : null;

        $email = trim((string) ($data['email'] ?? '')) ?: null;

        $contact = ClientContact::create([
            'company_id' => $this->companyId,
            'client_id' => $client->id,
            'client_contact_job_title_id' => $this->resolveJobTitle($jobTitleName, $stats),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'main_contact' => (bool) ($data['is_primary'] ?? false),
            'timesheet_contact' => (bool) ($data['is_timesheet'] ?? false),
            'invoice_contact' => (bool) ($data['is_invoice'] ?? false),
            'booking_contact' => (bool) ($data['is_booking'] ?? false),
        ]);

        $stats['contacts_created']++;

        if (! ($data['can_login'] ?? false) || ! $email) {
            return;
        }

        $this->ensureLoginForContact($contact, $email, $stats);
    }

    /** @param array<string, mixed> $stats */
    private function ensureLoginForContact(ClientContact $contact, string $email, array &$stats): void
    {
        $key = Str::lower($email);

        if (isset($this->loginUserIdsByEmail[$key])) {
            return;
        }

        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$key])->first();

        if ($existingUser) {
            $this->loginUserIdsByEmail[$key] = $existingUser->id;

            return;
        }

        $user = User::create([
            'name' => trim($contact->first_name.' '.$contact->last_name),
            'email' => $email,
            'password' => self::INITIAL_PASSWORD,
            'requires_account_setup' => true,
            'company_id' => $this->companyId,
            'client_contact_id' => $contact->id,
        ]);
        $user->assignRole('client');

        $this->loginUserIdsByEmail[$key] = $user->id;
        $stats['logins_created']++;
    }

    /** @param array<string, mixed> $stats */
    private function resolveClientType(string $name, array &$stats): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        if (isset($this->clientTypeIds[$name])) {
            return $this->clientTypeIds[$name];
        }

        if ($this->dryRun) {
            $existing = ClientType::query()
                ->where('company_id', $this->companyId)
                ->where('industry_id', $this->industryId)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $this->clientTypeIds[$name] = $existing->id;

                return $existing->id;
            }

            return null;
        }

        $clientType = ClientType::firstOrCreate([
            'company_id' => $this->companyId,
            'industry_id' => $this->industryId,
            'name' => $name,
        ]);

        if ($clientType->wasRecentlyCreated) {
            $stats['client_types_created']++;
        }

        $this->clientTypeIds[$name] = $clientType->id;

        return $clientType->id;
    }

    /** @param array<string, mixed> $stats */
    private function resolveJobTitle(string $name, array &$stats): ?int
    {
        $name = trim($name);

        if ($name === '' || strcasecmp($name, 'Select') === 0) {
            return null;
        }

        if (isset($this->jobTitleIds[$name])) {
            return $this->jobTitleIds[$name];
        }

        if ($this->dryRun) {
            $existing = ClientContactJobTitle::query()
                ->where('company_id', $this->companyId)
                ->where('industry_id', $this->industryId)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $this->jobTitleIds[$name] = $existing->id;

                return $existing->id;
            }

            return null;
        }

        $jobTitle = ClientContactJobTitle::firstOrCreate([
            'company_id' => $this->companyId,
            'industry_id' => $this->industryId,
            'name' => $name,
        ]);

        if ($jobTitle->wasRecentlyCreated) {
            $stats['job_titles_created']++;
        }

        $this->jobTitleIds[$name] = $jobTitle->id;

        return $jobTitle->id;
    }

    /** @return array<int, string> */
    private function resolveKeyStages(string $raw): array
    {
        $raw = strtoupper(trim($raw));

        if ($raw === '') {
            return [];
        }

        if ($raw === 'SEN') {
            return [KeyStage::SEN->value];
        }

        $tokens = str_contains($raw, ',') ? array_map('trim', explode(',', $raw)) : str_split($raw);

        $map = [
            'N' => KeyStage::Nursery,
            '1' => KeyStage::KeyStage1,
            '2' => KeyStage::KeyStage2,
            '3' => KeyStage::KeyStage3,
            '4' => KeyStage::KeyStage4,
            '5' => KeyStage::KeyStage5,
        ];

        return collect($tokens)
            ->map(fn (string $token): ?KeyStage => $map[$token] ?? null)
            ->filter()
            ->map(fn (KeyStage $keyStage): string => $keyStage->value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolves each distinct target consultant name in CONSULTANT_NAME_MAP to a
     * real user id, creating the account only if no matching person exists at
     * all — matched by name regardless of role, since a real staff member (e.g.
     * a company admin) may already exist without the "consultant" role. Grants
     * the "consultant" role if they don't already have it, so the Client
     * resource's consultant picker (which filters by that role) finds them,
     * without touching whatever other roles they already hold.
     */
    private function resolveOrCreateConsultants(): void
    {
        $targetNames = collect(self::CONSULTANT_NAME_MAP)->unique()->values();

        foreach ($targetNames as $targetName) {
            $user = User::query()
                ->where('company_id', $this->companyId)
                ->whereRaw('LOWER(name) = ?', [Str::lower($targetName)])
                ->first();

            if ($user) {
                if (! $this->dryRun && ! $user->hasRole('consultant')) {
                    $user->assignRole('consultant');
                }

                $this->consultantIds[$targetName] = $user->id;

                continue;
            }

            if ($this->dryRun) {
                $this->line("Would create consultant \"{$targetName}\".");

                continue;
            }

            $firstName = Str::of($targetName)->before(' ')->lower()->toString();
            $email = $firstName.'@'.self::CONSULTANT_EMAIL_DOMAIN;

            $user = User::create([
                'name' => $targetName,
                'email' => $email,
                'password' => self::INITIAL_PASSWORD,
                'requires_account_setup' => true,
                'company_id' => $this->companyId,
            ]);
            $user->assignRole('consultant');

            $this->consultantIds[$targetName] = $user->id;
            $this->line("Created consultant \"{$targetName}\" (user id {$user->id}, {$email}).");
        }
    }

    /** @param array<string, mixed> $stats */
    private function resolveConsultantId(mixed $sourceName, array &$stats): ?int
    {
        $key = Str::lower(trim((string) $sourceName));

        if ($key === '') {
            return null;
        }

        $targetName = self::CONSULTANT_NAME_MAP[$key] ?? null;

        if (! $targetName) {
            $stats['unresolved_consultants'][] = $sourceName;

            return null;
        }

        return $this->consultantIds[$targetName] ?? null;
    }
}
