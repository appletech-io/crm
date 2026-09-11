<?php

namespace App\Console\Commands;

use App\Enums\Integration;
use App\Models\Booking;
use App\Models\Client;
use App\Services\Payroll\Evertime\EvertimeClient;
use App\Services\Payroll\Evertime\Requests\GetClientContacts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off follow-up to this session's CSV-based client ID reconciliation
 * (see MapEvertimeClientIds): the 31 clients below just had their Evertime
 * ClientId repointed from a placeholder/wrong value to their real,
 * pre-existing Evertime customer record. That record already has its own
 * contacts under Evertime's own ClientContactIds, unrelated to whatever
 * this app has stored locally — so any future placement/timesheet call for
 * these clients would still send a stale contact ID Evertime doesn't
 * recognise for the new client (the exact "ApproverContactId ... does not
 * match" error that prompted this). This fetches each client's real
 * contacts from Evertime, matches them to our own ClientContact rows by
 * email, and reconciles the stored provider_external_id.
 */
#[Signature('evertime:reconcile-client-contacts {--commit : Actually store the resolved contact IDs — default is a dry run that only reports what it would do}')]
#[Description('Fetch each recently-remapped client\'s real contacts from Evertime and reconcile ClientContact.provider_external_id by matching on email')]
class ReconcileEvertimeClientContacts extends Command
{
    /**
     * Client IDs whose Evertime ClientId was repointed this session via the
     * CSV reconciliation — see the session's evertime_updates.json.
     *
     * @var array<int, int>
     */
    private const CLIENT_IDS = [
        141, 81, 54, 89, 140, 158, 206, 225, 24, 287, 178, 136, 319, 208, 19,
        164, 15, 47, 282, 107, 129, 145, 173, 181, 205, 214, 220, 221, 256,
        306, 307,
    ];

    private bool $commit = false;

    /** @var array<int, string> */
    private array $reconciled = [];

    /** @var array<int, string> */
    private array $alreadyCorrect = [];

    /** @var array<int, string> */
    private array $noEvertimeMatch = [];

    /** @var array<int, string> */
    private array $unmatchedEvertimeContacts = [];

    /** @var array<int, string> */
    private array $skipped = [];

    /** @var array<int, string> */
    private array $existingPlacements = [];

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');

        $clients = Client::query()->whereIn('id', self::CLIENT_IDS)->with('contacts')->get();

        if ($clients->isEmpty()) {
            $this->error('None of the configured client IDs exist.');

            return self::FAILURE;
        }

        $getContacts = new GetClientContacts(new EvertimeClient($clients->first()->company));

        foreach ($clients as $crmClient) {
            $this->reconcileClient($crmClient, $getContacts);
            $this->reportExistingPlacements($crmClient);
        }

        $this->printSummary();
        $this->writeReportFile();

        return self::SUCCESS;
    }

    private function reconcileClient(Client $crmClient, GetClientContacts $getContacts): void
    {
        $evertimeClientId = $crmClient->providerExternalId(Integration::Evertime);

        if (! $evertimeClientId) {
            $this->skipped[] = "Client #{$crmClient->id} {$crmClient->name}: no Evertime ClientId stored";

            return;
        }

        $evertimeContacts = $getContacts->handle($evertimeClientId);
        $evertimeByEmail = collect($evertimeContacts)
            ->filter(fn (array $c): bool => filled($c['Email'] ?? null))
            ->keyBy(fn (array $c): string => $this->normalizeEmail($c['Email']));

        $matchedEmails = [];

        foreach ($crmClient->contacts as $contact) {
            if (blank($contact->email)) {
                continue;
            }

            $email = $this->normalizeEmail($contact->email);
            $evertimeContact = $evertimeByEmail->get($email);

            if (! $evertimeContact) {
                $this->noEvertimeMatch[] = "Client #{$crmClient->id} {$crmClient->name} — contact {$contact->first_name} {$contact->last_name} ({$contact->email}): no matching Evertime contact by email";

                continue;
            }

            $matchedEmails[] = $email;
            $newId = (string) $evertimeContact['ClientContactId'];
            $existingId = $contact->providerExternalId(Integration::Evertime);

            if ($existingId === $newId) {
                $this->alreadyCorrect[] = "Client #{$crmClient->id} {$crmClient->name} — {$contact->email}: already {$newId}";

                continue;
            }

            $this->reconciled[] = "Client #{$crmClient->id} {$crmClient->name} — {$contact->email}: {$existingId} -> {$newId}";

            if ($this->commit) {
                $contact->setProviderExternalId(Integration::Evertime, $newId);
            }
        }

        foreach ($evertimeByEmail as $email => $evertimeContact) {
            if (in_array($email, $matchedEmails, true)) {
                continue;
            }

            $this->unmatchedEvertimeContacts[] = "Client #{$crmClient->id} {$crmClient->name} — Evertime contact {$evertimeContact['Forename']} {$evertimeContact['Surname']} ({$evertimeContact['Email']}, id {$evertimeContact['ClientContactId']}): no matching local contact by email";
        }
    }

    /**
     * Purely informational — a booking already having a stored Evertime
     * PlacementId means a placement (and possibly timesheets) was already
     * created against this client under whatever contact was stale at the
     * time, so it may need manual attention directly in Evertime. Nothing
     * here is auto-corrected.
     */
    private function reportExistingPlacements(Client $crmClient): void
    {
        $bookings = Booking::query()->where('client_id', $crmClient->id)->get();

        foreach ($bookings as $booking) {
            $placementId = $booking->providerExternalId(Integration::Evertime);

            if ($placementId) {
                $this->existingPlacements[] = "Client #{$crmClient->id} {$crmClient->name} — Booking #{$booking->id}: PlacementId {$placementId}";
            }
        }
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Mode', $this->commit ? 'LIVE — external IDs written' : 'DRY RUN (no writes)');
        $this->components->twoColumnDetail('Clients checked', (string) count(self::CLIENT_IDS));
        $this->components->twoColumnDetail($this->commit ? 'Reconciled' : 'Would reconcile', (string) count($this->reconciled));
        $this->components->twoColumnDetail('Already correct', (string) count($this->alreadyCorrect));
        $this->components->twoColumnDetail('Local contacts with no Evertime email match', (string) count($this->noEvertimeMatch));
        $this->components->twoColumnDetail('Evertime contacts with no local email match', (string) count($this->unmatchedEvertimeContacts));
        $this->components->twoColumnDetail('Skipped (no Evertime ClientId)', (string) count($this->skipped));
        $this->components->twoColumnDetail('Existing bookings with a stored PlacementId (may need manual review)', (string) count($this->existingPlacements));
        $this->newLine();

        foreach ($this->reconciled as $line) {
            $this->line("  - {$line}");
        }

        $this->info('Full detail is in the report file below.');
    }

    private function writeReportFile(): void
    {
        $lines = [
            'Mode: '.($this->commit ? 'LIVE' : 'DRY RUN'),
            'Generated: '.now()->toDateTimeString(),
            '',
            '== Reconciled ('.count($this->reconciled).') ==',
            ...$this->reconciled,
            '',
            '== Already correct ('.count($this->alreadyCorrect).') ==',
            ...$this->alreadyCorrect,
            '',
            '== Local contacts with no Evertime email match ('.count($this->noEvertimeMatch).') ==',
            ...$this->noEvertimeMatch,
            '',
            '== Evertime contacts with no local email match ('.count($this->unmatchedEvertimeContacts).') ==',
            ...$this->unmatchedEvertimeContacts,
            '',
            '== Skipped ('.count($this->skipped).') ==',
            ...$this->skipped,
            '',
            '== Existing bookings with a stored PlacementId — may need manual review in Evertime ('.count($this->existingPlacements).') ==',
            ...$this->existingPlacements,
        ];

        $path = storage_path('app/evertime-contact-reconciliation-'.now()->format('Y-m-d-His').'.txt');
        file_put_contents($path, implode(PHP_EOL, $lines));

        $this->line("Report written to: {$path}");
    }
}
