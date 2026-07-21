<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Enums\ReferenceType;
use App\Models\CandidateActivity;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EducationCandidateSeeder extends Seeder
{
    private const TOTAL = 35;

    /**
     * Roughly what proportion of candidates land in each stage of the
     * pipeline, so the demo shows every stage rather than a uniform pile.
     * Weights are chosen so every stage gets at least one candidate.
     *
     * @var array<string, float>
     */
    private const TIER_WEIGHTS = [
        'onboarding' => 0.15,
        'vetting' => 0.2,
        'live' => 0.4,
        'dnu' => 0.1,
        'offline' => 0.15,
    ];

    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $consultants = User::role('consultant')->where('company_id', $company->id)->get();
        $admin = User::role('admin')->where('company_id', $company->id)->firstOrFail();
        $qualifications = Qualification::where('company_id', $company->id)->get();
        $jobTitles = JobTitle::where('company_id', $company->id)->get();
        $skills = CandidateSkill::where('company_id', $company->id)->whereNotNull('parent_id')->get();
        $pools = CandidatePool::where('company_id', $company->id)->get();
        $statuses = CandidateStatus::where('company_id', $company->id)->get()->keyBy('name');

        $tiers = $this->weightedTiers();

        for ($i = 0; $i < self::TOTAL; $i++) {
            $tier = $tiers[$i];

            $candidate = EducationCandidate::factory()->create([
                'company_id' => $company->id,
                'title' => fake()->randomElement(['Mr', 'Mrs', 'Miss', 'Ms', 'Dr']),
                'gender' => fake()->randomElement(['male', 'female', 'non_binary']),
                'nationality' => fake()->randomElement(['British', 'British', 'British', 'Irish', 'Polish', 'Nigerian', 'Indian']),
                'date_of_birth' => fake()->dateTimeBetween('-58 years', '-22 years'),
                'address' => fake()->streetAddress(),
                'city' => fake()->city(),
                'county' => fake()->randomElement(['West Midlands', 'Greater London', 'Greater Manchester', 'West Yorkshire']),
                'postcode' => fake()->postcode(),
                'country' => 'United Kingdom',
                'mobile' => '07'.fake()->numerify('#########'),
                'consultant_id' => $consultants->random()->id,
                'qualification_id' => $qualifications->random()->id,
                'key_stages' => collect(KeyStage::cases())
                    ->random(random_int(1, 3))
                    ->map(fn (KeyStage $stage): string => $stage->value)
                    ->values()
                    ->all(),
                'availability' => collect(Availability::cases())
                    ->random(random_int(1, 3))
                    ->map(fn (Availability $case): string => $case->value)
                    ->values()
                    ->all(),
                'notes' => fake()->optional(0.6)->sentence(),
                'has_health_condition_or_disability' => 'no',
                'retired_early' => 'no',
                'dismissed_from_relevant_position' => 'no',
                'subject_to_disciplinary_action' => 'no',
                'lived_overseas_six_months' => 'no',
                'unspent_convictions' => 'no',
                'spent_convictions_not_protected' => 'no',
                'right_to_work_type' => 'passport',
                ...$this->tierAttributes($tier, $admin),
            ]);

            $this->attachSkills($candidate, $skills);
            $this->attachPools($candidate, $pools, $tier);
            $this->createReferences($candidate);
            $this->createEmploymentHistory($candidate);
            $this->createDocuments($candidate, $tier);
            $this->assignStatuses($candidate, $statuses, $tier);
            $this->createPayRates($candidate, $company->id, $jobTitles);
            $this->createActivities($candidate, $consultants);
        }
    }

    /** @return array<int, string> */
    private function weightedTiers(): array
    {
        $tiers = [];

        foreach (self::TIER_WEIGHTS as $tier => $weight) {
            $tiers = array_merge($tiers, array_fill(0, (int) round(self::TOTAL * $weight), $tier));
        }

        while (count($tiers) < self::TOTAL) {
            $tiers[] = 'live';
        }

        shuffle($tiers);

        return array_slice($tiers, 0, self::TOTAL);
    }

    /** @return array<string, mixed> */
    private function tierAttributes(string $tier, User $admin): array
    {
        $fullyCompliant = [
            'compliance_step' => 6,
            'barred_list_check' => 'yes',
            'barred_list_check_date' => now()->subDays(random_int(20, 90)),
            'has_dbs' => 'yes',
            'dbs_certificate_number' => fake()->numerify('############'),
            'dbs_checked_date' => now()->subDays(random_int(20, 90)),
            'update_service_response' => 'Clear',
            'update_service_checked_at' => now()->subDays(random_int(20, 90)),
            'safeguarding_certified_date' => now()->subDays(random_int(30, 200)),
            'prevent_training_completed' => 'yes',
            'trn_number' => fake()->numerify('#######'),
            'trn_issue_date' => now()->subYears(random_int(1, 8)),
            'compliance_completed_at' => now()->subDays(random_int(5, 60)),
            'compliance_completed_by' => $admin->id,
        ];

        return match ($tier) {
            'onboarding' => [
                'compliance_step' => 1,
                'barred_list_check' => 'yes',
            ],
            'vetting' => [
                'compliance_step' => 3,
                'barred_list_check' => 'yes',
                'barred_list_check_date' => now()->subDays(random_int(1, 20)),
            ],
            'live', 'offline' => $fullyCompliant,
            'dnu' => [
                'compliance_step' => 2,
                'barred_list_check' => 'no',
                'barred_list_check_date' => now()->subDays(random_int(1, 30)),
            ],
            default => [],
        };
    }

    /** @param  Collection<int, CandidateSkill>  $skills */
    private function attachSkills(EducationCandidate $candidate, Collection $skills): void
    {
        if ($skills->isEmpty()) {
            return;
        }

        $candidate->skills()->attach($skills->random(min($skills->count(), random_int(2, 4)))->pluck('id'));
    }

    /** @param  Collection<int, CandidatePool>  $pools */
    private function attachPools(EducationCandidate $candidate, Collection $pools, string $tier): void
    {
        if ($pools->isEmpty() || $tier === 'onboarding') {
            return;
        }

        $candidate->candidatePools()->attach($pools->random(random_int(0, 2))->pluck('id'));
    }

    private function createReferences(EducationCandidate $candidate): void
    {
        foreach (range(1, random_int(1, 2)) as $i) {
            $candidate->references()->create([
                'type' => $i === 1 ? ReferenceType::Professional->value : ReferenceType::Character->value,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'job_title' => fake()->randomElement(['Headteacher', 'Deputy Head', 'HR Manager', 'Line Manager']),
                'worked_from' => fake()->dateTimeBetween('-8 years', '-3 years'),
                'worked_to' => fake()->dateTimeBetween('-3 years', '-1 year'),
                'email' => fake()->unique()->safeEmail(),
                'mobile' => '07'.fake()->numerify('#########'),
                'consent_to_contact' => true,
                'contact_now' => true,
                'status' => fake()->randomElement(['pending', 'contacted', 'confirmed']),
            ]);
        }
    }

    private function createEmploymentHistory(EducationCandidate $candidate): void
    {
        $schools = ['Oakfield Primary', 'Riverside Academy', 'Greenhill School', 'St. Andrew\'s College', 'Meadowview School'];

        $cursor = now()->subYears(random_int(3, 10));

        foreach (range(1, random_int(1, 3)) as $i) {
            $from = $cursor->copy();
            $to = $from->copy()->addMonths(random_int(8, 30));

            $candidate->employmentHistories()->create([
                'company_name' => fake()->randomElement($schools),
                'job_title' => fake()->randomElement(['Class Teacher', 'Teaching Assistant', 'Cover Supervisor', 'HLTA']),
                'worked_from' => $from,
                'worked_to' => $to,
            ]);

            $cursor = $to->copy()->addMonths(random_int(1, 6));
        }
    }

    private function createDocuments(EducationCandidate $candidate, string $tier): void
    {
        // Placeholder paths only — no real files are stored, so downloading
        // these in the demo will 404. Good enough to show "Uploaded" badges.
        $types = [DocumentType::Cv, DocumentType::Photo, DocumentType::ProofOfAddress];

        if (in_array($tier, ['live', 'offline'], true)) {
            $types = [...$types, DocumentType::DbsFront, DocumentType::DbsBack, DocumentType::ProofOfNi];
        }

        foreach ($types as $type) {
            $candidate->documents()->create([
                'document_type' => $type->value,
                'path' => "demo/candidates/{$candidate->id}/{$type->value}.pdf",
            ]);
        }
    }

    /** @param  Collection<string, CandidateStatus>  $statuses */
    private function assignStatuses(EducationCandidate $candidate, Collection $statuses, string $tier): void
    {
        $name = match ($tier) {
            'onboarding' => 'Onboarding',
            'vetting' => 'Vetting',
            'live' => 'Live',
            'dnu' => 'DNU',
            'offline' => 'Offline',
            default => 'Onboarding',
        };

        $status = $statuses->get($name);

        if (! $status) {
            return;
        }

        $candidate->statuses()->create(['candidate_status_id' => $status->id]);
    }

    /** @param  Collection<int, JobTitle>  $jobTitles */
    private function createPayRates(EducationCandidate $candidate, int $companyId, Collection $jobTitles): void
    {
        foreach ($jobTitles->random(random_int(1, 2)) as $jobTitle) {
            $candidate->payRates()->create([
                'company_id' => $companyId,
                'job_title_id' => $jobTitle->id,
                'hourly_rate' => fake()->randomFloat(2, 11, 20),
                'day_rate' => fake()->randomFloat(2, 90, 160),
                'half_day_rate' => fake()->randomFloat(2, 50, 90),
            ]);
        }
    }

    /** @param  Collection<int, User>  $consultants */
    private function createActivities(EducationCandidate $candidate, Collection $consultants): void
    {
        $notes = [
            ActivityType::Call->value => 'Called to check availability for the coming weeks.',
            ActivityType::Email->value => 'Sent compliance checklist and next steps.',
            ActivityType::Note->value => 'Prefers primary school placements, has own transport.',
        ];

        foreach (fake()->randomElements(array_keys($notes), random_int(2, 3)) as $type) {
            CandidateActivity::create([
                'user_id' => $consultants->random()->id,
                'model_type' => EducationCandidate::class,
                'model_id' => $candidate->id,
                'type' => $type,
                'note' => $notes[$type],
                'contacted' => $type !== ActivityType::Note->value,
                'created_at' => Carbon::now()->subDays(random_int(1, 45)),
            ]);
        }
    }
}
