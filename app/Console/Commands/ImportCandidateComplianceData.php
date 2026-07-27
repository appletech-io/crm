<?php

namespace App\Console\Commands;

use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Enums\Nationality;
use App\Models\CandidateCandidateStatus;
use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

#[Signature('import:candidate-compliance {path=candidate_details_compliance.jsonl} {--dry-run : Report what would happen without writing anything to the database}')]
#[Description('Import the legacy candidate_details_compliance.jsonl export into education_candidates for Applebough')]
class ImportCandidateComplianceData extends Command
{
    private const string COMPANY_NAME = 'Applebough';

    private const string INDUSTRY_SLUG = 'education';

    /**
     * Source consultant_name value => the real consultant's name to resolve
     * an id for. Resolved dynamically at runtime (not hardcoded ids) since
     * user ids differ between environments.
     *
     * @var array<string, string|null>
     */
    private const array CONSULTANT_NAME_MAP = [
        'ashley greaves' => 'ashley greaves',
        'charm dube' => 'ashley greaves',
        'kirsty greaves' => 'kirsty greaves',
        'kirsty two' => 'kirsty greaves',
        'syed shah' => null,
    ];

    /** @var array<string, string> */
    private const array NATIONALITY_FIXES = [
        'ghanian' => 'Ghanaian',
        'india' => 'Indian',
        'nigeria' => 'Nigerian',
        'norweigan' => 'Norwegian',
        'zimbabian' => 'Zimbabwean',
        'argentinian' => 'Argentinean',
        'yemeni' => 'Yemenite',
    ];

    private bool $dryRun = false;

    private int $companyId;

    /** @var array<string, int> */
    private array $consultantIds = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $path = base_path($this->argument('path'));

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $companyId = Company::query()->where('name', self::COMPANY_NAME)->value('id');
        $industryId = Industry::query()->where('slug', self::INDUSTRY_SLUG)->value('id');

        if (! $companyId || ! $industryId) {
            $this->error('Could not resolve company "'.self::COMPANY_NAME.'" or industry "'.self::INDUSTRY_SLUG.'" on this database.');

            return self::FAILURE;
        }

        $this->companyId = $companyId;

        if (! $this->resolveConsultantIds($companyId)) {
            return self::FAILURE;
        }

        $vettingStatusId = $this->statusId('Vetting', $companyId, $industryId);
        $onboardingStatusId = $this->statusId('Onboarding', $companyId, $industryId);

        if (! $vettingStatusId || ! $onboardingStatusId) {
            $this->error("Could not find Vetting/Onboarding CandidateStatus records for company {$companyId} / industry {$industryId}.");

            return self::FAILURE;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            $this->error("Could not read file: {$path}");

            return self::FAILURE;
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'no_email' => 0,
            'unmatched_nationalities' => [],
            'seen_emails' => [],
            'duplicate_emails' => [],
        ];

        $this->withProgressBar($lines, function (string $line) use (&$stats, $vettingStatusId, $onboardingStatusId): void {
            $record = json_decode($line, true);

            if (! is_array($record)) {
                return;
            }

            $this->importRecord($record, $vettingStatusId, $onboardingStatusId, $stats);
        });

        $this->newLine(2);

        $this->components->twoColumnDetail('Mode', $this->dryRun ? 'DRY RUN (no writes)' : 'LIVE');
        $this->components->twoColumnDetail('Created', (string) $stats['created']);
        $this->components->twoColumnDetail('Updated', (string) $stats['updated']);
        $this->components->twoColumnDetail('Skipped (no email)', (string) $stats['no_email']);
        $this->components->twoColumnDetail('Duplicate emails within file', (string) count($stats['duplicate_emails']));

        if (! empty($stats['duplicate_emails'])) {
            $this->warn('Duplicate emails (last occurrence in the file wins): '.implode(', ', $stats['duplicate_emails']));
        }

        if (! empty($stats['unmatched_nationalities'])) {
            $this->warn('Unmatched nationalities (left null): '.implode(', ', array_unique($stats['unmatched_nationalities'])));
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $stats */
    private function importRecord(array $record, int $vettingStatusId, int $onboardingStatusId, array &$stats): void
    {
        $details = $record['details'] ?? [];
        $availabilitySkills = $record['availability_skills'] ?? [];
        $compliance = $record['compliance'] ?? [];

        $email = trim((string) ($details['email'] ?? ''));

        $seenBefore = $email !== '' && in_array($email, $stats['seen_emails'], true);

        if ($email === '') {
            $stats['no_email']++;
        } elseif ($seenBefore) {
            $stats['duplicate_emails'][] = $email;
        } else {
            $stats['seen_emails'][] = $email;
        }

        $attributes = $this->mapAttributes($details, $availabilitySkills, $compliance, $stats);

        if ($this->dryRun) {
            $exists = $seenBefore || ($email !== '' && EducationCandidate::query()
                ->where('company_id', $this->companyId)
                ->where('email', $email)
                ->exists());

            $stats[$exists ? 'updated' : 'created']++;

            $this->assignStatus(null, $compliance, $vettingStatusId, $onboardingStatusId);

            return;
        }

        if ($email !== '') {
            $candidate = EducationCandidate::query()
                ->where('company_id', $this->companyId)
                ->where('email', $email)
                ->first();

            if ($candidate) {
                $candidate->update($attributes);
                $stats['updated']++;
            } else {
                $candidate = EducationCandidate::create($attributes + ['company_id' => $this->companyId, 'email' => $email]);
                $stats['created']++;
            }
        } else {
            $candidate = EducationCandidate::create($attributes + ['company_id' => $this->companyId]);
            $stats['created']++;
        }

        $this->assignStatus($candidate, $compliance, $vettingStatusId, $onboardingStatusId);
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $availabilitySkills
     * @param  array<string, mixed>  $compliance
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function mapAttributes(array $details, array $availabilitySkills, array $compliance, array &$stats): array
    {
        $gender = match ($details['gender'] ?? null) {
            'Female' => 'female',
            'Male' => 'male',
            default => null,
        };

        $nationality = $this->resolveNationality($details['Nationality'] ?? null, $stats);

        $mobile = Arr::get($details, 'Mobile') ?: Arr::get($details, 'Mobile or Phone No');

        return [
            'title' => $details['title'] ?? null,
            'first_name' => $details['First Name'] ?? null,
            'middle_name' => $details['Middle Name'] ?? null,
            'last_name' => $details['Last Name'] ?? null,
            'previous_surname' => $details['Previous Surname'] ?? null,
            'gender' => $gender,
            'nationality' => $nationality,
            'date_of_birth' => $details['Date of Birth'] ?? null,
            'place_of_birth' => $details['Place of Birth'] ?? null,
            'emergency_contact_name' => $details['Emergency Contact Name'] ?? null,
            'address' => $details['Address'] ?? null,
            'postcode' => $details['Postcode'] ?? null,
            'city' => $details['City'] ?? null,
            'county' => $details['county'] ?? null,
            'country' => $details['country'] ?? null,
            'phone' => $details['Phone No'] ?? null,
            'mobile' => $mobile,
            'consultant_id' => $this->resolveConsultantId($details['consultant_name'] ?? null),
            'availability' => $this->resolveAvailability($availabilitySkills),
            'key_stages' => $this->resolveKeyStages($availabilitySkills),
            'education_and_qualification' => $availabilitySkills['education'] ?? null,
            'employment_history' => $availabilitySkills['employment_history'] ?? null,
            'notes' => $availabilitySkills['notes'] ?? null,
            ...$this->resolveComplianceAttributes($compliance),
        ];
    }

    /** @param array<string, mixed> $stats */
    private function resolveNationality(?string $value, array &$stats): ?string
    {
        if (! $value) {
            return null;
        }

        $fixed = self::NATIONALITY_FIXES[Str::lower(trim($value))] ?? $value;

        if (Nationality::tryFrom($fixed)) {
            return $fixed;
        }

        $stats['unmatched_nationalities'][] = $value;

        return null;
    }

    /** @param array<string, mixed> $availabilitySkills */
    private function resolveAvailability(array $availabilitySkills): array
    {
        return collect(Availability::cases())
            ->filter(fn (Availability $case): bool => ($availabilitySkills[$case->label()] ?? false) === true)
            ->map(fn (Availability $case): string => $case->value)
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $availabilitySkills */
    private function resolveKeyStages(array $availabilitySkills): array
    {
        $flags = [
            KeyStage::Nursery->value => (bool) ($availabilitySkills['Nursery'] ?? false),
            KeyStage::KeyStage1->value => (bool) ($availabilitySkills['Keystage 1'] ?? false) || (bool) ($availabilitySkills['Key Stage 1'] ?? false),
            KeyStage::KeyStage2->value => (bool) ($availabilitySkills['Keystage 2'] ?? false) || (bool) ($availabilitySkills['Key Stage 2'] ?? false),
            KeyStage::KeyStage3->value => (bool) ($availabilitySkills['Keystage 3'] ?? false),
            KeyStage::KeyStage4->value => (bool) ($availabilitySkills['Keystage 4'] ?? false),
            KeyStage::KeyStage5->value => (bool) ($availabilitySkills['Keystage 5'] ?? false),
            KeyStage::SEN->value => (bool) ($availabilitySkills['SEN'] ?? false),
        ];

        return collect($flags)->filter()->keys()->values()->all();
    }

    /** @param array<string, mixed> $compliance */
    private function resolveComplianceAttributes(array $compliance): array
    {
        $sanctions = match (true) {
            ($compliance['Any Sanctions'] ?? null) === 'No Sanctions' => 'no',
            filled($compliance['Any Sanctions'] ?? null) => 'yes',
            default => null,
        };

        $restrictions = match (true) {
            in_array($compliance['Any Restrictions'] ?? null, ['No Restrictions', 'No restrictions'], true) => 'no',
            filled($compliance['Any Restrictions'] ?? null) => 'yes',
            default => null,
        };

        $unspentConvictions = match ($compliance['CriminalRecord'] ?? null) {
            'Yes' => 'yes',
            'No' => 'no',
            default => null,
        };

        $overseasPoliceClearance = ($compliance['overseas_police_clearance'] ?? null) === 'yes' ? 'yes' : null;

        $rightToWorkType = match ($compliance['TypeofVisa'] ?? null) {
            'UK Passport' => 'passport',
            'UK Birth Certificate' => 'birth_certificate',
            'Student visa', 'Share Code Check', 'Visa' => 'visa',
            default => null,
        };

        $dbsNumber = $compliance['DBS No'] ?? null;
        $crbUpdate = $compliance['CRBUpdate'] ?? null;

        [$hasDbs, $updateServiceResponse] = match (true) {
            $crbUpdate === 'Cleared' => ['yes', 'BLANK_NO_NEW_INFO'],
            $crbUpdate === 'Update Service' => ['yes', null],
            filled($dbsNumber) => ['yes', null],
            default => ['no', null],
        };

        return [
            'sanctions' => $sanctions,
            'restrictions' => $restrictions,
            'unspent_convictions' => $unspentConvictions,
            'overseas_police_clearance_check' => $overseasPoliceClearance,
            'overseas_police_clearance_check_date' => $compliance['overseas_police_clearance_date'] ?? null,
            'right_to_work_type' => $rightToWorkType,
            'visa_expiry_date' => $compliance['Visa Expiry'] ?? null,
            'dbs_certificate_number' => $dbsNumber,
            'has_dbs' => $hasDbs,
            'update_service_response' => $updateServiceResponse,
            'update_service_checked_at' => $compliance['Update Service Issue Date'] ?? null,
            'safeguarding_certified_date' => $compliance['Safeguarding Date'] ?? null,
            'trn_number' => $compliance['TRN Number'] ?? null,
        ];
    }

    /** @param array<string, mixed> $compliance */
    private function assignStatus(?EducationCandidate $candidate, array $compliance, int $vettingStatusId, int $onboardingStatusId): void
    {
        $targetStatusId = ($compliance['status'] ?? null) === 'Live' ? $vettingStatusId : $onboardingStatusId;

        if ($this->dryRun || ! $candidate) {
            return;
        }

        $currentStatusId = $candidate->statuses()->value('candidate_status_id');

        if ($currentStatusId === $targetStatusId) {
            return;
        }

        CandidateCandidateStatus::create([
            'model_type' => EducationCandidate::class,
            'model_id' => $candidate->id,
            'candidate_status_id' => $targetStatusId,
        ]);
    }

    private function statusId(string $name, int $companyId, int $industryId): ?int
    {
        return CandidateStatus::query()
            ->where('company_id', $companyId)
            ->where('industry_id', $industryId)
            ->where('name', $name)
            ->value('id');
    }

    /**
     * Resolves each distinct target consultant name in CONSULTANT_NAME_MAP
     * to a real user id, once, up front — rather than trusting hardcoded
     * ids that differ between environments. Fails loudly if a name can't
     * be resolved unambiguously, rather than silently assigning the wrong
     * consultant to candidates.
     */
    private function resolveConsultantIds(int $companyId): bool
    {
        $targetNames = collect(self::CONSULTANT_NAME_MAP)->filter()->unique()->values();

        foreach ($targetNames as $targetName) {
            $matches = User::role('consultant')
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [$targetName])
                ->get(['id', 'name', 'email']);

            if ($matches->isEmpty()) {
                $this->error("No consultant found named \"{$targetName}\" for this company. Aborting.");

                return false;
            }

            if ($matches->count() > 1) {
                $this->error("Multiple consultants named \"{$targetName}\" found — ambiguous. Aborting: ".
                    $matches->map(fn (User $user): string => "id {$user->id} ({$user->email})")->implode(', '));

                return false;
            }

            $this->consultantIds[$targetName] = $matches->first()->id;
        }

        foreach ($this->consultantIds as $name => $id) {
            $this->line("Resolved consultant \"{$name}\" to user id {$id}.");
        }

        return true;
    }

    private function resolveConsultantId(?string $sourceName): ?int
    {
        $targetName = self::CONSULTANT_NAME_MAP[Str::lower(trim((string) $sourceName))] ?? null;

        if (! $targetName) {
            return null;
        }

        return $this->consultantIds[$targetName] ?? null;
    }
}
