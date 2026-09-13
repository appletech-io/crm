<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\ComplianceItemDataType;
use App\Enums\ReferenceType;
use App\Enums\VacancyEmploymentType;
use App\Models\Booking;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientType;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use App\Models\Industry;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyPlacement;
use App\Services\Booking\TimesheetPeriod;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A third demo company — Vantage Point Recruitment — fleshing out two
 * industries that (unlike Education/Healthcare) run on the *generic*
 * Candidate model rather than a bespoke one:
 *
 * - Construction: temp/day-rate work filled via Booking, same as Education/
 *   Healthcare, but leaning hard on the generic Compliance Items system
 *   (CSCS, H&S induction, manual handling, first aid) — the thing that
 *   system exists to support without a bespoke candidate model per sector.
 * - IT: no Bookings at all — permanent and contract roles filled via the
 *   Vacancy → VacancyApplication (→ shortlisted) → VacancyPlacement
 *   pipeline instead. "Contract" here uses VacancyEmploymentType::Temp with
 *   day_rate_min/max set rather than salary, since the enum has no distinct
 *   Contract case — the closest existing fit, at the cost of
 *   Vacancy::estimatedPlacementValue()/actualPlacementValue() returning
 *   null for those specific roles (their margin isn't fee-on-salary based,
 *   same reasoning that already excludes genuine Temp/Booking roles).
 *
 * Run standalone (php artisan db:seed --class=VantagePointDemoSeeder), not
 * part of DatabaseSeeder's default chain — this creates its own company
 * from scratch, same as BrightPathSeeder.
 */
class VantagePointDemoSeeder extends Seeder
{
    private Company $company;

    private Industry $construction;

    private Industry $it;

    /** @var Collection<int, User> */
    private Collection $consultants;

    public function run(): void
    {
        $this->construction = Industry::firstOrCreate(['slug' => 'construction'], ['name' => 'Construction']);
        $this->it = Industry::firstOrCreate(['slug' => 'it'], ['name' => 'IT']);

        $this->company = Company::create([
            'name' => 'Vantage Point Recruitment',
            'trading_name' => 'Vantage Point Recruitment Group',
            'phone' => '0117 555 0163',
        ]);

        $this->company->industries()->attach([$this->construction->id, $this->it->id]);

        $this->seedUsers();
        $this->seedJobTitles();
        $this->seedClientTypes();
        $this->seedCandidateSkills();
        $this->seedCandidateStatuses();
        $this->seedJobStatuses();
        $this->seedCandidatePools();

        $constructionComplianceItems = $this->seedConstructionComplianceItems();
        $this->seedItComplianceItems();

        $constructionClients = $this->seedClients($this->construction, [
            'Redwood Construction Ltd',
            'Sterling Build Group',
            'Ashcroft Civil Engineering',
            'Pinnacle Housebuilders',
            'Marlow Groundworks',
        ]);

        $itClients = $this->seedClients($this->it, [
            'NexaSoft Solutions',
            'BlueWave Digital',
            'Meridian Tech Partners',
            'Orbit Cloud Services',
            'Fenwick Systems',
        ]);

        $constructionCandidates = $this->seedConstructionCandidates(30, $constructionComplianceItems);
        $itCandidates = $this->seedItCandidates(25);

        $this->seedPoolMembers($this->construction, $constructionCandidates, 'Groundworks Ready');
        $this->seedPoolMembers($this->construction, $constructionCandidates, 'CSCS Certified');
        $this->seedPoolMembers($this->it, $itCandidates, 'Cloud Specialists');
        $this->seedPoolMembers($this->it, $itCandidates, 'Immediately Available');

        $this->seedCurrentPeriodBookings($constructionClients, $constructionCandidates);
        $this->seedSpreadBookings($constructionClients, $constructionCandidates, 35);

        $this->seedItVacancies($itClients, $itCandidates);

        $this->command?->info('Vantage Point Recruitment (Construction + IT) seeded.');
    }

    private function seedUsers(): void
    {
        $admin = User::factory()->create([
            'name' => 'Morgan Hale',
            'email' => 'admin@vantagepoint.test',
            'company_id' => $this->company->id,
        ]);
        $admin->assignRole('admin');
        $admin->industries()->attach([$this->construction->id, $this->it->id]);

        foreach (['Owen Patterson', 'Freya Nolan'] as $name) {
            $consultant = User::factory()->create([
                'name' => $name,
                'email' => str($name)->slug('.').'@vantagepoint.test',
                'company_id' => $this->company->id,
            ]);
            $consultant->assignRole('consultant');
            $consultant->industries()->attach([$this->construction->id, $this->it->id]);
        }

        $resourcer = User::factory()->create([
            'name' => 'Callum Ibrahim',
            'email' => 'resourcer@vantagepoint.test',
            'company_id' => $this->company->id,
        ]);
        $resourcer->assignRole('resourcer');
        $resourcer->industries()->attach([$this->construction->id, $this->it->id]);

        $this->consultants = User::role('consultant')->where('company_id', $this->company->id)->get();
    }

    private function seedJobTitles(): void
    {
        $titles = [
            $this->construction->id => ['Labourer', 'Groundworker', 'Scaffolder', 'Electrician', 'Plumber', 'Site Supervisor', 'Plant Operator', 'Bricklayer', 'Carpenter', 'Health & Safety Officer'],
            $this->it->id => ['Software Developer', 'DevOps Engineer', 'IT Support Technician', 'Systems Administrator', 'Project Manager', 'Business Analyst', 'QA Engineer', 'Cloud Architect'],
        ];

        foreach ($titles as $industryId => $names) {
            foreach ($names as $name) {
                JobTitle::firstOrCreate([
                    'company_id' => $this->company->id,
                    'industry_id' => $industryId,
                    'name' => $name,
                ]);
            }
        }
    }

    private function seedClientTypes(): void
    {
        $types = [
            $this->construction->id => ['Main Contractor', 'Subcontractor', 'Housebuilder', 'Civil Engineering Firm'],
            $this->it->id => ['Software House', 'Managed Service Provider', 'Enterprise IT Department', 'Start-up'],
        ];

        foreach ($types as $industryId => $names) {
            foreach ($names as $name) {
                ClientType::firstOrCreate([
                    'company_id' => $this->company->id,
                    'industry_id' => $industryId,
                    'name' => $name,
                ]);
            }
        }
    }

    private function seedCandidateSkills(): void
    {
        $skills = [
            $this->construction->id => ['Groundworks', 'Scaffolding', 'Working at Height', 'Manual Handling', 'Plant Operation', 'Bricklaying', 'Electrical Installation', 'Plumbing & Heating'],
            $this->it->id => ['JavaScript', 'Python', 'Cloud Infrastructure (AWS/Azure)', 'Networking', 'Database Administration', 'DevOps / CI-CD', 'Technical Support', 'Project Delivery'],
        ];

        foreach ($skills as $industryId => $names) {
            foreach ($names as $name) {
                CandidateSkill::firstOrCreate([
                    'company_id' => $this->company->id,
                    'industry_id' => $industryId,
                    'name' => $name,
                    'parent_id' => null,
                ]);
            }
        }
    }

    /**
     * Construction mirrors the Onboarding/Vetting/Live/DNU/Offline shape
     * every other industry in this app uses — new, industry-scoped rows,
     * not shared with anyone else's — while IT swaps Vetting for a Placed
     * status (is_filled_status, so Vacancy::placementsFilled can compute
     * correctly once a candidate is placed into a role).
     */
    private function seedCandidateStatuses(): void
    {
        $constructionStatuses = [
            'Onboarding' => 'amber',
            'Vetting' => 'blue',
            'Live' => 'emerald',
            'DNU' => 'red',
            'Offline' => 'gray',
        ];

        foreach ($constructionStatuses as $name => $color) {
            CandidateStatus::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $this->construction->id,
                'name' => $name,
            ], ['color' => $color]);
        }

        $itStatuses = [
            'Onboarding' => ['color' => 'amber', 'is_filled_status' => false],
            'Live' => ['color' => 'emerald', 'is_filled_status' => false],
            'Placed' => ['color' => 'sky', 'is_filled_status' => true],
            'DNU' => ['color' => 'red', 'is_filled_status' => false],
            'Offline' => ['color' => 'gray', 'is_filled_status' => false],
        ];

        foreach ($itStatuses as $name => $attributes) {
            CandidateStatus::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $this->it->id,
                'name' => $name,
            ], $attributes);
        }
    }

    /**
     * A candidate-progress pipeline (Shortlisted → ... → Placed) rather than
     * a vacancy-state list (Open/On Hold/Filled/Cancelled) — matches how the
     * Job Pipeline Flow dashboard widget is meant to read for an industry
     * with no Bookings, where "job status" tracks where the placement
     * process for that role has got to.
     */
    private function seedJobStatuses(): void
    {
        $statuses = [
            'Shortlisted' => ['color' => 'gray', 'is_filled_status' => false],
            'Sent' => ['color' => 'blue', 'is_filled_status' => false],
            'Interview Stage 1' => ['color' => 'indigo', 'is_filled_status' => false],
            'Interview Stage 2+' => ['color' => 'violet', 'is_filled_status' => false],
            'Offered' => ['color' => 'amber', 'is_filled_status' => false],
            'Placed' => ['color' => 'emerald', 'is_filled_status' => true],
        ];

        foreach (array_keys($statuses) as $sortOrder => $name) {
            JobStatus::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $this->it->id,
                'name' => $name,
            ], [
                ...$statuses[$name],
                'sort_order' => $sortOrder,
            ]);
        }
    }

    private function seedCandidatePools(): void
    {
        CandidatePool::firstOrCreate([
            'company_id' => $this->company->id,
            'industry_id' => $this->construction->id,
            'name' => 'Groundworks Ready',
        ], ['company_pool' => true]);

        CandidatePool::firstOrCreate([
            'company_id' => $this->company->id,
            'industry_id' => $this->construction->id,
            'name' => 'CSCS Certified',
        ], ['company_pool' => true]);

        CandidatePool::firstOrCreate([
            'company_id' => $this->company->id,
            'industry_id' => $this->it->id,
            'name' => 'Cloud Specialists',
        ], ['company_pool' => true]);

        CandidatePool::firstOrCreate([
            'company_id' => $this->company->id,
            'industry_id' => $this->it->id,
            'name' => 'Immediately Available',
        ], ['company_pool' => true]);
    }

    /**
     * Five compliance items covering most of a construction site worker's
     * real paperwork — three of them (CSCS, H&S Induction, Manual Handling)
     * attached to nearly every trade job title, First Aid only to the
     * supervisory ones, so forJobTitle() eligibility genuinely varies by
     * role rather than every candidate needing everything.
     *
     * @return array<string, ComplianceItem>
     */
    private function seedConstructionComplianceItems(): array
    {
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $this->construction->id)->get()->keyBy('name');

        $items = [
            'CSCS Card' => [
                'fields' => [
                    ['name' => 'Card Number', 'data_type' => ComplianceItemDataType::Text],
                    ['name' => 'Expiry Date', 'data_type' => ComplianceItemDataType::DateExpiry],
                ],
                'job_titles' => ['Groundworker', 'Scaffolder', 'Bricklayer', 'Carpenter', 'Plant Operator', 'Site Supervisor', 'Electrician', 'Plumber'],
            ],
            'Health & Safety Induction' => [
                'fields' => [
                    ['name' => 'Completion Date', 'data_type' => ComplianceItemDataType::Date],
                ],
                'job_titles' => ['Labourer', 'Groundworker', 'Scaffolder', 'Bricklayer', 'Carpenter', 'Plant Operator', 'Site Supervisor', 'Electrician', 'Plumber', 'Health & Safety Officer'],
            ],
            'Manual Handling Certificate' => [
                'fields' => [
                    ['name' => 'Issue Date', 'data_type' => ComplianceItemDataType::Date],
                    ['name' => 'Expiry Date', 'data_type' => ComplianceItemDataType::DateExpiry],
                ],
                'job_titles' => ['Labourer', 'Groundworker', 'Bricklayer', 'Carpenter', 'Plant Operator'],
            ],
            'Right to Work' => [
                'fields' => [
                    ['name' => 'Document', 'data_type' => ComplianceItemDataType::Document],
                    ['name' => 'Expiry Date', 'data_type' => ComplianceItemDataType::DateExpiry],
                ],
                'job_titles' => $jobTitles->keys()->all(),
            ],
            'First Aid at Work' => [
                'fields' => [
                    ['name' => 'Certificate', 'data_type' => ComplianceItemDataType::Document],
                    ['name' => 'Expiry Date', 'data_type' => ComplianceItemDataType::DateExpiry],
                ],
                'job_titles' => ['Site Supervisor', 'Health & Safety Officer'],
            ],
        ];

        $created = [];

        foreach ($items as $name => $config) {
            $item = ComplianceItem::create([
                'company_id' => $this->company->id,
                'industry_id' => $this->construction->id,
                'name' => $name,
            ]);

            foreach ($config['fields'] as $field) {
                ComplianceItemField::create([
                    'compliance_item_id' => $item->id,
                    'name' => $field['name'],
                    'data_type' => $field['data_type']->value,
                ]);
            }

            foreach ($config['job_titles'] as $jobTitleName) {
                $jobTitle = $jobTitles->get($jobTitleName);

                if ($jobTitle) {
                    $item->jobTitles()->attach($jobTitle->id, [
                        'company_id' => $this->company->id,
                        'industry_id' => $this->construction->id,
                    ]);
                }
            }

            $created[$name] = $item->fresh('fields');
        }

        return $created;
    }

    /**
     * IT gets a single, universal item — deliberately light, in contrast to
     * Construction's five — since the point of this demo split is showing
     * the same generic system flexing to very different compliance loads
     * per industry, not that IT has none at all.
     */
    private function seedItComplianceItems(): void
    {
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $this->it->id)->get();

        $item = ComplianceItem::create([
            'company_id' => $this->company->id,
            'industry_id' => $this->it->id,
            'name' => 'Right to Work',
        ]);

        ComplianceItemField::create([
            'compliance_item_id' => $item->id,
            'name' => 'Document',
            'data_type' => ComplianceItemDataType::Document->value,
        ]);
        ComplianceItemField::create([
            'compliance_item_id' => $item->id,
            'name' => 'Expiry Date',
            'data_type' => ComplianceItemDataType::DateExpiry->value,
        ]);

        foreach ($jobTitles as $jobTitle) {
            $item->jobTitles()->attach($jobTitle->id, [
                'company_id' => $this->company->id,
                'industry_id' => $this->it->id,
            ]);
        }
    }

    /**
     * @param  array<int, string>  $names
     * @return Collection<int, Client>
     */
    private function seedClients(Industry $industry, array $names): Collection
    {
        $clientTypes = ClientType::where('company_id', $this->company->id)->where('industry_id', $industry->id)->get();
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $industry->id)->get();

        return collect($names)->map(function (string $name) use ($industry, $clientTypes, $jobTitles): Client {
            $client = Client::factory()->create([
                'company_id' => $this->company->id,
                'industry_id' => $industry->id,
                'name' => $name,
                'client_type_id' => $clientTypes->random()->id,
                'consultant_id' => $this->consultants->random()->id,
                'postcode' => null,
            ]);

            $client->contacts()->create([
                'company_id' => $this->company->id,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->unique()->safeEmail(),
                'main_contact' => true,
                'booking_contact' => true,
            ]);

            foreach ($jobTitles->random(min(3, $jobTitles->count())) as $jobTitle) {
                PayRate::create([
                    'company_id' => $this->company->id,
                    'model_type' => Client::class,
                    'model_id' => $client->id,
                    'job_title_id' => $jobTitle->id,
                    'hourly_rate' => fake()->randomFloat(2, 12, 28),
                    'day_rate' => fake()->randomFloat(2, 100, 220),
                    'half_day_rate' => fake()->randomFloat(2, 55, 120),
                ]);
            }

            ClientActivity::create([
                'user_id' => $this->consultants->random()->id,
                'model_type' => Client::class,
                'model_id' => $client->id,
                'type' => ActivityType::Call->value,
                'note' => 'Called to confirm upcoming requirements.',
                'contacted' => true,
                'created_at' => now()->subDays(random_int(1, 30)),
            ]);

            return $client;
        });
    }

    /** @var array<string, float> */
    private const TIER_WEIGHTS = [
        'onboarding' => 0.15,
        'vetting' => 0.2,
        'live' => 0.45,
        'dnu' => 0.08,
        'offline' => 0.12,
    ];

    /** @return array<int, string> */
    private function weightedTiers(int $total): array
    {
        $tiers = [];

        foreach (self::TIER_WEIGHTS as $tier => $weight) {
            $tiers = array_merge($tiers, array_fill(0, (int) round($total * $weight), $tier));
        }

        while (count($tiers) < $total) {
            $tiers[] = 'live';
        }

        shuffle($tiers);

        return array_slice($tiers, 0, $total);
    }

    private function statusNameForTier(string $tier): string
    {
        return match ($tier) {
            'onboarding' => 'Onboarding',
            'vetting' => 'Vetting',
            'live' => 'Live',
            'dnu' => 'DNU',
            'offline' => 'Offline',
            default => 'Onboarding',
        };
    }

    /**
     * @param  array<string, ComplianceItem>  $complianceItems
     * @return Collection<int, Candidate>
     */
    private function seedConstructionCandidates(int $total, array $complianceItems): Collection
    {
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $this->construction->id)->get();
        $skills = CandidateSkill::where('company_id', $this->company->id)->where('industry_id', $this->construction->id)->get();
        $statuses = CandidateStatus::where('company_id', $this->company->id)->where('industry_id', $this->construction->id)->get()->keyBy('name');
        $tiers = $this->weightedTiers($total);

        return collect(range(1, $total))->map(function (int $n, int $i) use ($jobTitles, $skills, $statuses, $tiers, $complianceItems): Candidate {
            $tier = $tiers[$i];
            $jobTitle = $jobTitles->random();

            $candidate = Candidate::factory()->create([
                'company_id' => $this->company->id,
                'industry_id' => $this->construction->id,
                'consultant_id' => $this->consultants->random()->id,
                'job_title_id' => $jobTitle->id,
            ]);

            $this->attachCommonCandidateData($candidate, $skills, $statuses, $tier);
            $this->attachComplianceValues($candidate, $jobTitle, $complianceItems, $tier);

            return $candidate;
        });
    }

    /** @return Collection<int, Candidate> */
    private function seedItCandidates(int $total): Collection
    {
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $this->it->id)->get();
        $skills = CandidateSkill::where('company_id', $this->company->id)->where('industry_id', $this->it->id)->get();
        $statuses = CandidateStatus::where('company_id', $this->company->id)->where('industry_id', $this->it->id)->get()->keyBy('name');
        $tiers = collect($this->weightedTiers($total))
            // IT has no "Vetting" status — anything that tier assigned just
            // becomes Onboarding instead, keeping the same overall spread.
            ->map(fn (string $tier): string => $tier === 'vetting' ? 'onboarding' : $tier)
            ->all();

        return collect(range(1, $total))->map(function (int $n, int $i) use ($jobTitles, $skills, $statuses, $tiers): Candidate {
            $tier = $tiers[$i];

            $candidate = Candidate::factory()->create([
                'company_id' => $this->company->id,
                'industry_id' => $this->it->id,
                'consultant_id' => $this->consultants->random()->id,
                'job_title_id' => $jobTitles->random()->id,
            ]);

            if ($skills->isNotEmpty()) {
                $candidate->skills()->attach($skills->random(min(3, $skills->count()))->pluck('id'));
            }

            $candidate->references()->create([
                'type' => ReferenceType::Professional->value,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'job_title' => 'Line Manager',
                'worked_from' => now()->subYears(3),
                'worked_to' => now()->subYear(),
                'email' => fake()->unique()->safeEmail(),
                'consent_to_contact' => true,
                'contact_now' => true,
                'status' => 'pending',
            ]);

            $candidate->employmentHistories()->create([
                'company_name' => fake()->company(),
                'job_title' => 'Previous Role',
                'worked_from' => now()->subYears(4),
                'worked_to' => now()->subYears(1),
            ]);

            $status = $statuses->get($this->statusNameForTier($tier));

            if ($status) {
                $candidate->statuses()->create(['candidate_status_id' => $status->id]);
            }

            CandidateActivity::create([
                'user_id' => $this->consultants->random()->id,
                'model_type' => $candidate::class,
                'model_id' => $candidate->id,
                'type' => ActivityType::Call->value,
                'note' => 'Called to discuss suitable roles.',
                'contacted' => true,
                'created_at' => now()->subDays(random_int(1, 30)),
            ]);

            return $candidate;
        });
    }

    /** @param Collection<int, CandidateSkill> $skills
     * @param  Collection<string, CandidateStatus>  $statuses
     */
    private function attachCommonCandidateData(Candidate $candidate, Collection $skills, Collection $statuses, string $tier): void
    {
        if ($skills->isNotEmpty()) {
            $candidate->skills()->attach($skills->random(min(3, $skills->count()))->pluck('id'));
        }

        $candidate->references()->create([
            'type' => ReferenceType::Professional->value,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'job_title' => 'Site Manager',
            'worked_from' => now()->subYears(4),
            'worked_to' => now()->subYear(),
            'email' => fake()->unique()->safeEmail(),
            'consent_to_contact' => true,
            'contact_now' => true,
            'status' => 'pending',
        ]);

        $candidate->employmentHistories()->create([
            'company_name' => fake()->company(),
            'job_title' => 'Previous Role',
            'worked_from' => now()->subYears(5),
            'worked_to' => now()->subYears(2),
        ]);

        $status = $statuses->get($this->statusNameForTier($tier));

        if ($status) {
            $candidate->statuses()->create(['candidate_status_id' => $status->id]);
        }

        CandidateActivity::create([
            'user_id' => $this->consultants->random()->id,
            'model_type' => $candidate::class,
            'model_id' => $candidate->id,
            'type' => ActivityType::Call->value,
            'note' => 'Called to check availability for site work.',
            'contacted' => true,
            'created_at' => now()->subDays(random_int(1, 30)),
        ]);
    }

    /**
     * Deliberately varied compliance completeness — this is the whole point
     * of the Construction side of this seeder. A DNU/Offline candidate gets
     * nothing filled in (never progressed), Onboarding gets partial data
     * (mid-process), and Live/Vetting candidates are mostly complete but
     * with roughly a third of their DateExpiry values already
     * expired/expiring, so ComplianceRequirements' expiry-warning logic
     * (and the compliance badges built on it) actually has something to
     * flag rather than showing a uniform "all green" board.
     *
     * @param  array<string, ComplianceItem>  $complianceItems
     */
    private function attachComplianceValues(Candidate $candidate, JobTitle $jobTitle, array $complianceItems, string $tier): void
    {
        if (in_array($tier, ['dnu', 'offline'], true)) {
            return;
        }

        $required = $jobTitle->complianceItems()->with('fields')->get();

        foreach ($required as $item) {
            /** @var ComplianceItem $fullItem */
            $fullItem = $complianceItems[$item->name] ?? $item;

            foreach ($fullItem->fields as $field) {
                // Onboarding candidates are still mid-process — only fill in
                // roughly half their required fields, so some items show as
                // incomplete rather than everyone being either 0% or 100%.
                if ($tier === 'onboarding' && random_int(1, 100) > 50) {
                    continue;
                }

                $expiringSoon = random_int(1, 100) <= 30;

                $candidate->complianceValues()->create([
                    'compliance_item_field_id' => $field->id,
                    'text_value' => $field->data_type === ComplianceItemDataType::Text ? fake()->bothify('??######') : null,
                    'date_value' => in_array($field->data_type, [ComplianceItemDataType::Date, ComplianceItemDataType::DateExpiry], true)
                        ? ($field->data_type === ComplianceItemDataType::DateExpiry
                            ? ($expiringSoon ? now()->subDays(random_int(0, 30)) : now()->addMonths(random_int(3, 24)))
                            : now()->subDays(random_int(30, 300)))
                        : null,
                    'document_path' => $field->data_type === ComplianceItemDataType::Document ? "demo/candidates/{$candidate->id}/{$field->name}.pdf" : null,
                    'document_name' => $field->data_type === ComplianceItemDataType::Document ? "{$field->name}.pdf" : null,
                    'completed_at' => now()->subDays(random_int(1, 60)),
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, Candidate>  $candidates
     */
    private function seedPoolMembers(Industry $industry, Collection $candidates, string $poolName): void
    {
        $pool = CandidatePool::where('company_id', $this->company->id)
            ->where('industry_id', $industry->id)
            ->where('name', $poolName)
            ->firstOrFail();

        $pool->candidatesOfType(Candidate::class)->syncWithoutDetaching(
            $candidates->random(min(10, $candidates->count()))->pluck('id')->all()
        );
    }

    /**
     * A deliberate mix of client-approval states in the current payroll
     * period, matching the same red/green demo pattern used for Education/
     * Healthcare's booking data this session.
     *
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, Candidate>  $candidates
     */
    private function seedCurrentPeriodBookings(Collection $clients, Collection $candidates): void
    {
        $period = TimesheetPeriod::current($this->company);
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $this->construction->id)->get();

        $pattern = ['approved', 'approved', 'sent', 'pending', 'disputed'];
        $chosenClients = $clients->shuffle()->take(min(5, $clients->count()))->values();

        foreach ($chosenClients as $i => $client) {
            foreach (range(1, random_int(1, 2)) as $n) {
                $booking = Booking::create([
                    'company_id' => $this->company->id,
                    'client_id' => $client->id,
                    'candidate_id' => $candidates->random()->id,
                    'candidate_type' => Candidate::class,
                    'job_title_id' => $jobTitles->isNotEmpty() ? $jobTitles->random()->id : null,
                    'consultant_id' => $this->consultants->random()->id,
                    'start_date' => $period['start'],
                    'status' => BookingStatus::Upcoming,
                    'hourly_rate' => fake()->randomFloat(2, 12, 25),
                    'day_rate' => fake()->randomFloat(2, 100, 190),
                    'day_charge_rate' => fake()->randomFloat(2, 150, 260),
                ]);

                foreach (CarbonPeriod::create($period['start'], $period['end']) as $date) {
                    if ($date->isWeekend()) {
                        continue;
                    }

                    $booking->dayPeriods()->create($this->dayAttributesFor($pattern[$i % count($pattern)], $date));
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function dayAttributesFor(string $pattern, Carbon $date): array
    {
        $base = [
            'company_id' => $this->company->id,
            'date' => $date->toDateString(),
            'period' => BookingDayPeriod::FullDay,
        ];

        return match ($pattern) {
            'approved' => [...$base, 'payroll_confirmation_sent_at' => now()->subDays(3), 'approved_at' => now()->subDay()],
            'sent' => [...$base, 'payroll_confirmation_sent_at' => now()->subDays(2)],
            'disputed' => [...$base, 'payroll_confirmation_sent_at' => now()->subDays(3), 'disputed_at' => now()->subDay(), 'dispute_reason' => 'Hours queried by site manager.'],
            default => $base,
        };
    }

    /**
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, Candidate>  $candidates
     */
    private function seedSpreadBookings(Collection $clients, Collection $candidates, int $total): void
    {
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $this->construction->id)->get();

        for ($i = 0; $i < $total; $i++) {
            $startDate = now()->subWeeks(6)->addDays(random_int(0, 12 * 7));
            $isPast = $startDate->isPast();

            $booking = Booking::create([
                'company_id' => $this->company->id,
                'client_id' => $clients->random()->id,
                'candidate_id' => $candidates->random()->id,
                'candidate_type' => Candidate::class,
                'job_title_id' => $jobTitles->isNotEmpty() ? $jobTitles->random()->id : null,
                'consultant_id' => $this->consultants->random()->id,
                'start_date' => $startDate,
                'status' => $isPast ? 'completed' : 'upcoming',
                'hourly_rate' => fake()->randomFloat(2, 12, 25),
                'day_rate' => fake()->randomFloat(2, 100, 190),
                // The charge rate billed to the client — see the sibling
                // current-period method above, which already gets this
                // right. Without it, every booking here nets a guaranteed
                // negative gross profit (full pay cost, nothing billed to
                // cover it).
                'day_charge_rate' => fake()->randomFloat(2, 150, 260),
            ]);

            $booking->dayPeriods()->create([
                'company_id' => $this->company->id,
                'date' => $startDate,
                'period' => 'full_day',
                'payroll_confirmation_sent_at' => $isPast ? $startDate->copy()->addDays(7) : null,
                'approved_at' => $isPast ? $startDate->copy()->addDays(8) : null,
            ]);
        }
    }

    /**
     * Nine vacancies — five Permanent, four "Contract" (Temp employment
     * type with a day rate instead of a salary, see the class docblock) —
     * each with several applications (some merely applied, some
     * shortlisted), and about half fully placed: a VacancyPlacement per
     * position, the placed candidate's status flipped to "Placed", and the
     * vacancy itself marked Placed.
     *
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, Candidate>  $candidates
     */
    private function seedItVacancies(Collection $clients, Collection $candidates): void
    {
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $this->it->id)->get()->keyBy('name');
        $jobStatuses = JobStatus::where('company_id', $this->company->id)->where('industry_id', $this->it->id)->get()->keyBy('name');
        $placedStatus = CandidateStatus::where('company_id', $this->company->id)->where('industry_id', $this->it->id)->where('name', 'Placed')->first();

        $vacancies = [
            ['title' => 'Senior Backend Developer', 'job_title' => 'Software Developer', 'type' => VacancyEmploymentType::Permanent, 'filled' => true],
            ['title' => 'IT Support Technician', 'job_title' => 'IT Support Technician', 'type' => VacancyEmploymentType::Permanent, 'filled' => true],
            ['title' => 'Business Analyst', 'job_title' => 'Business Analyst', 'type' => VacancyEmploymentType::Permanent, 'filled' => false],
            ['title' => 'Cloud Architect', 'job_title' => 'Cloud Architect', 'type' => VacancyEmploymentType::Permanent, 'filled' => false],
            ['title' => 'QA Engineer', 'job_title' => 'QA Engineer', 'type' => VacancyEmploymentType::Permanent, 'filled' => false],
            ['title' => 'Contract DevOps Engineer — 6 Month', 'job_title' => 'DevOps Engineer', 'type' => VacancyEmploymentType::Temp, 'filled' => true],
            ['title' => 'Interim Project Manager — Day Rate', 'job_title' => 'Project Manager', 'type' => VacancyEmploymentType::Temp, 'filled' => true],
            ['title' => 'Contract Systems Administrator', 'job_title' => 'Systems Administrator', 'type' => VacancyEmploymentType::Temp, 'filled' => false],
            ['title' => 'Contract Cloud Architect', 'job_title' => 'Cloud Architect', 'type' => VacancyEmploymentType::Temp, 'filled' => false],
        ];

        foreach ($vacancies as $config) {
            $client = $clients->random();
            $jobTitle = $jobTitles->get($config['job_title']);
            $isTemp = $config['type'] === VacancyEmploymentType::Temp;

            $vacancy = Vacancy::create([
                'company_id' => $this->company->id,
                'industry_id' => $this->it->id,
                'client_id' => $client->id,
                'job_title_id' => $jobTitle?->id,
                'consultant_id' => $this->consultants->random()->id,
                'title' => $config['title'],
                'slug' => Vacancy::generateUniqueSlug($config['title']),
                'description' => $isTemp
                    ? "Day-rate contract engagement via {$client->name}, initial term with scope to extend."
                    : "Permanent opportunity with {$client->name}.",
                'salary_min' => $isTemp ? null : fake()->numberBetween(35000, 55000),
                'salary_max' => $isTemp ? null : fake()->numberBetween(60000, 90000),
                'day_rate_min' => $isTemp ? fake()->numberBetween(350, 450) : null,
                'day_rate_max' => $isTemp ? fake()->numberBetween(450, 650) : null,
                'positions_available' => 1,
                'employment_type' => $config['type']->value,
                'placement_fee_percentage' => $isTemp ? null : fake()->randomFloat(2, 15, 22),
                'open_for_applications' => ! $config['filled'],
                'job_status_id' => $jobStatuses->get($config['filled'] ? 'Placed' : 'Shortlisted')?->id,
                'filled_at' => $config['filled'] ? now()->subDays(random_int(1, 20)) : null,
            ]);

            $applicantPool = $candidates->filter(fn (Candidate $c): bool => $c->job_title_id === $jobTitle?->id);
            $applicants = $applicantPool->isNotEmpty() ? $applicantPool : $candidates->random(min(4, $candidates->count()));
            $applicants = $applicants->shuffle()->take(min(5, $applicants->count()));

            $placedCandidate = null;

            foreach ($applicants->values() as $index => $candidate) {
                $shortlisted = $index < 2;
                $willBePlaced = $config['filled'] && $index === 0;

                VacancyApplication::create([
                    'vacancy_id' => $vacancy->id,
                    'candidate_type' => Candidate::class,
                    'candidate_id' => $candidate->id,
                    'match_strength' => fake()->numberBetween(50, 95),
                    'shortlisted_at' => $shortlisted || $willBePlaced ? now()->subDays(random_int(2, 15)) : null,
                ]);

                if ($willBePlaced) {
                    $placedCandidate = $candidate;
                }
            }

            if ($config['filled'] && $placedCandidate) {
                VacancyPlacement::create([
                    'vacancy_id' => $vacancy->id,
                    'candidate_type' => Candidate::class,
                    'candidate_id' => $placedCandidate->id,
                    'actual_salary' => $isTemp ? null : fake()->numberBetween(38000, 85000),
                    'placed_at' => now()->subDays(random_int(1, 20)),
                ]);

                if ($placedStatus) {
                    $placedCandidate->statuses()->create(['candidate_status_id' => $placedStatus->id]);
                }
            }
        }
    }
}
