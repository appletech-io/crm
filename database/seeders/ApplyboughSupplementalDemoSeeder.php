<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\BookingDayPeriod;
use App\Enums\BookingStatus;
use App\Enums\CandidateAvailabilityStatus;
use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Enums\Healthcare\CareSetting;
use App\Enums\ReferenceType;
use App\Models\Booking;
use App\Models\CandidateActivity;
use App\Models\CandidatePool;
use App\Models\CandidateSkill;
use App\Models\CandidateStatus;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientType;
use App\Models\Company;
use App\Models\EducationCandidate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Models\Qualification;
use App\Models\User;
use App\Services\Booking\TimesheetPeriod;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Additive demo data for the primary "applebough" company (id 1) — the one
 * actually browsed day to day, as opposed to the separate "Bright Path
 * Recruitment" demo company BrightPathSeeder builds from scratch. Run
 * standalone (php artisan db:seed --class=ApplyboughSupplementalDemoSeeder),
 * not part of DatabaseSeeder's default chain, since it's meant to build on
 * whatever is already in the database rather than run against a fresh
 * install.
 *
 * Fills two gaps found when checking the database this data supports:
 * this company had no Healthcare data at all (Education + IT only), and its
 * current payroll period had a single client with five Pending days — not
 * enough to see the Run Payroll client-group colouring, or the availability
 * grid's week navigation, actually doing anything.
 */
class ApplyboughSupplementalDemoSeeder extends Seeder
{
    private Company $company;

    private Industry $education;

    private Industry $healthcare;

    /** @var Collection<int, User> */
    private Collection $consultants;

    public function run(): void
    {
        $this->company = Company::findOrFail(1);
        $this->education = Industry::where('slug', 'education')->firstOrFail();
        $this->healthcare = Industry::firstOrCreate(['slug' => 'healthcare'], ['name' => 'Healthcare']);

        $this->company->industries()->syncWithoutDetaching([$this->healthcare->id]);

        $this->consultants = User::role('consultant')->where('company_id', $this->company->id)->get();

        $this->seedHealthcareTaxonomy();

        $healthcareClients = $this->seedHealthcareClients();
        $healthcareCandidates = $this->seedHealthcareCandidates(28);
        $this->seedPools($this->healthcare, $healthcareCandidates, 'Healthcare — Weekend Cover Available');

        $this->seedCurrentPeriodBookings($healthcareClients, $healthcareCandidates, $this->healthcare);
        $this->seedSpreadBookings($healthcareClients, $healthcareCandidates, $this->healthcare, 30);

        $educationClients = Client::where('company_id', $this->company->id)->where('industry_id', $this->education->id)->get();
        $educationCandidates = $this->seedMoreEducationCandidates(25);
        $this->seedPools($this->education, $educationCandidates, 'Education — Recently Onboarded');
        $this->seedAvailability($educationCandidates);

        $this->seedCurrentPeriodBookings($educationClients, $educationCandidates, $this->education);
        $this->seedSpreadBookings($educationClients, $educationCandidates, $this->education, 30);

        $this->command?->info('Supplemental demo data seeded for company #1 (applebough).');
    }

    private function seedHealthcareTaxonomy(): void
    {
        $jobTitles = ['Registered Nurse', 'Healthcare Assistant', 'Support Worker', 'Care Coordinator', 'Domiciliary Carer', 'Senior Carer'];
        foreach ($jobTitles as $name) {
            JobTitle::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $this->healthcare->id,
                'name' => $name,
            ]);
        }

        $clientTypes = ['Hospital', 'Care Home', 'Domiciliary Care Provider', 'Mental Health Unit', 'GP Surgery'];
        foreach ($clientTypes as $name) {
            ClientType::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $this->healthcare->id,
                'name' => $name,
            ]);
        }

        $qualifications = ['NMC Registered Nurse', 'HCPC Registered', 'Care Certificate', 'NVQ Level 2 Health & Social Care', 'NVQ Level 3 Health & Social Care'];
        foreach ($qualifications as $name) {
            Qualification::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $this->healthcare->id,
                'name' => $name,
            ]);
        }

        $skills = ['Medication Administration', 'Wound Care', 'Manual Handling', 'Dementia Care', 'Palliative Care', 'Catheter Care'];
        foreach ($skills as $name) {
            CandidateSkill::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $this->healthcare->id,
                'name' => $name,
                'parent_id' => null,
            ]);
        }

        $statuses = [
            'Onboarding' => 'amber',
            'Vetting' => 'blue',
            'Live' => 'emerald',
            'DNU' => 'red',
            'Offline' => 'gray',
        ];
        foreach ($statuses as $name => $color) {
            CandidateStatus::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $this->healthcare->id,
                'name' => $name,
            ], ['color' => $color]);
        }
    }

    /** @return Collection<int, Client> */
    private function seedHealthcareClients(): Collection
    {
        $names = [
            'Northgate General Hospital',
            'Willowbrook Care Home',
            'Home First Domiciliary Care',
            'Meridian Mental Health Unit',
            'Riverside GP Surgery',
            'Fernbank Nursing Home',
        ];

        $clientTypes = ClientType::where('company_id', $this->company->id)->where('industry_id', $this->healthcare->id)->get();
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $this->healthcare->id)->get();

        return collect($names)->map(function (string $name) use ($clientTypes, $jobTitles): Client {
            $client = Client::factory()->create([
                'company_id' => $this->company->id,
                'industry_id' => $this->healthcare->id,
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
                    'hourly_rate' => fake()->randomFloat(2, 13, 22),
                    'day_rate' => fake()->randomFloat(2, 100, 180),
                    'half_day_rate' => fake()->randomFloat(2, 55, 100),
                ]);
            }

            ClientActivity::create([
                'user_id' => $this->consultants->random()->id,
                'model_type' => Client::class,
                'model_id' => $client->id,
                'type' => ActivityType::Call->value,
                'note' => 'Called to confirm shift requirements for the coming weeks.',
                'contacted' => true,
                'created_at' => now()->subDays(random_int(1, 30)),
            ]);

            return $client;
        });
    }

    /**
     * Roughly what proportion of candidates land in each stage of the
     * pipeline — mirrors BrightPathSeeder's own weighting.
     *
     * @var array<string, float>
     */
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

    /** @return Collection<int, HealthcareCandidate> */
    private function seedHealthcareCandidates(int $total): Collection
    {
        $qualifications = Qualification::where('company_id', $this->company->id)->where('industry_id', $this->healthcare->id)->get();
        $skills = CandidateSkill::where('company_id', $this->company->id)->where('industry_id', $this->healthcare->id)->get();
        $statuses = CandidateStatus::where('company_id', $this->company->id)->where('industry_id', $this->healthcare->id)->get()->keyBy('name');
        $tiers = $this->weightedTiers($total);

        return collect(range(1, $total))->map(function (int $n, int $i) use ($qualifications, $skills, $statuses, $tiers): HealthcareCandidate {
            $tier = $tiers[$i];

            $candidate = HealthcareCandidate::factory()->create([
                'company_id' => $this->company->id,
                'consultant_id' => $this->consultants->random()->id,
                'qualification_id' => $qualifications->random()->id,
                'care_settings' => collect(CareSetting::cases())->random(2)->map(fn (CareSetting $c) => $c->value)->values()->all(),
                'availability' => collect(Availability::cases())->random(2)->map(fn (Availability $c) => $c->value)->values()->all(),
                'right_to_work_type' => 'passport',
                'professional_registration_body' => 'NMC',
                'professional_registration_number' => fake()->bothify('??########'),
                'professional_registration_checked_at' => now()->subDays(random_int(10, 90)),
            ]);

            $this->attachCommonCandidateData($candidate, $skills, $statuses, $tier);

            return $candidate;
        });
    }

    /** @return Collection<int, EducationCandidate> */
    private function seedMoreEducationCandidates(int $total): Collection
    {
        $qualifications = Qualification::where('company_id', $this->company->id)->where('industry_id', $this->education->id)->get();
        $skills = CandidateSkill::where('company_id', $this->company->id)->where('industry_id', $this->education->id)->get();
        $statuses = CandidateStatus::where('company_id', $this->company->id)->where('industry_id', $this->education->id)->get()->keyBy('name');
        $tiers = $this->weightedTiers($total);

        return collect(range(1, $total))->map(function (int $n, int $i) use ($qualifications, $skills, $statuses, $tiers): EducationCandidate {
            $tier = $tiers[$i];

            $candidate = EducationCandidate::factory()->create([
                'company_id' => $this->company->id,
                'consultant_id' => $this->consultants->random()->id,
                'qualification_id' => $qualifications->isNotEmpty() ? $qualifications->random()->id : null,
                'key_stages' => collect(KeyStage::cases())->random(2)->map(fn (KeyStage $c) => $c->value)->values()->all(),
                'availability' => collect(Availability::cases())->random(2)->map(fn (Availability $c) => $c->value)->values()->all(),
                'barred_list_check' => $tier === 'dnu' ? 'no' : 'yes',
                'right_to_work_type' => 'passport',
                'has_dbs' => $tier === 'onboarding' ? null : 'yes',
                'dbs_expiry_date' => $tier === 'onboarding' ? null : now()->addMonths(random_int(-2, 18)),
                'right_to_work_expiry_date' => now()->addMonths(random_int(6, 24)),
            ]);

            $this->attachCommonCandidateData($candidate, $skills, $statuses, $tier);

            return $candidate;
        });
    }

    /**
     * @param  EducationCandidate|HealthcareCandidate  $candidate
     * @param  Collection<int, CandidateSkill>  $skills
     * @param  Collection<string, CandidateStatus>  $statuses
     */
    private function attachCommonCandidateData($candidate, Collection $skills, Collection $statuses, string $tier): void
    {
        if ($skills->isNotEmpty()) {
            $candidate->skills()->attach($skills->random(min(3, $skills->count()))->pluck('id'));
        }

        $candidate->references()->create([
            'type' => ReferenceType::Professional->value,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'job_title' => 'Line Manager',
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

        $candidate->documents()->create([
            'document_type' => DocumentType::Cv->value,
            'path' => "demo/candidates/{$candidate->id}/cv.pdf",
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
            'note' => 'Called to check availability.',
            'contacted' => true,
            'created_at' => now()->subDays(random_int(1, 30)),
        ]);
    }

    /**
     * @param  Collection<int, EducationCandidate|HealthcareCandidate>  $candidates
     */
    private function seedPools(Industry $industry, Collection $candidates, string $poolName): void
    {
        $pool = CandidatePool::firstOrCreate([
            'company_id' => $this->company->id,
            'industry_id' => $industry->id,
            'name' => $poolName,
        ], ['company_pool' => true]);

        $candidateType = $industry->slug === 'education' ? EducationCandidate::class : HealthcareCandidate::class;

        $pool->candidatesOfType($candidateType)->syncWithoutDetaching(
            $candidates->random(min(10, $candidates->count()))->pluck('id')->all()
        );
    }

    /**
     * Weekday-only availability across last week, this week, and next week —
     * enough to see the availability grid actually change when navigating
     * between weeks. Roughly a third of weekdays are deliberately left
     * unset, since "no data" (the "?" icon) is itself a real, common state
     * this data should still show.
     *
     * @param  Collection<int, EducationCandidate>  $candidates
     */
    private function seedAvailability(Collection $candidates): void
    {
        $weeks = [
            now()->startOfWeek(Carbon::MONDAY)->subWeek(),
            now()->startOfWeek(Carbon::MONDAY),
            now()->startOfWeek(Carbon::MONDAY)->addWeek(),
        ];

        $statuses = [
            CandidateAvailabilityStatus::Available,
            CandidateAvailabilityStatus::Available,
            CandidateAvailabilityStatus::AvailableAm,
            CandidateAvailabilityStatus::AvailablePm,
            CandidateAvailabilityStatus::NotAvailable,
        ];

        foreach ($candidates->random(min(20, $candidates->count())) as $candidate) {
            foreach ($weeks as $weekStart) {
                foreach (range(0, 4) as $offset) {
                    if (random_int(1, 100) > 65) {
                        continue;
                    }

                    $candidate->availabilities()->create([
                        'date' => $weekStart->copy()->addDays($offset)->toDateString(),
                        'status' => fake()->randomElement($statuses)->value,
                    ]);
                }
            }
        }
    }

    /**
     * A deliberate mix of client-approval states in the current payroll
     * period — some clients fully approved (green group), some awaiting
     * confirmation, one disputed — so Run Payroll's grouping/highlighting
     * and ViewPayroll's chase list both have something real to show.
     *
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, EducationCandidate|HealthcareCandidate>  $candidates
     */
    private function seedCurrentPeriodBookings(Collection $clients, Collection $candidates, Industry $industry): void
    {
        if ($clients->isEmpty() || $candidates->isEmpty()) {
            return;
        }

        $period = TimesheetPeriod::current($this->company);
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $industry->id)->get();
        $candidateType = $industry->slug === 'education' ? EducationCandidate::class : HealthcareCandidate::class;

        $pattern = ['approved', 'approved', 'sent', 'pending', 'disputed', 'pending'];
        $chosenClients = $clients->shuffle()->take(min(6, $clients->count()))->values();

        foreach ($chosenClients as $i => $client) {
            foreach (range(1, random_int(1, 2)) as $n) {
                $booking = Booking::create([
                    'company_id' => $this->company->id,
                    'client_id' => $client->id,
                    'candidate_id' => $candidates->random()->id,
                    'candidate_type' => $candidateType,
                    'job_title_id' => $jobTitles->isNotEmpty() ? $jobTitles->random()->id : null,
                    'consultant_id' => $this->consultants->random()->id,
                    'start_date' => $period['start'],
                    'status' => BookingStatus::Upcoming,
                    'hourly_rate' => fake()->randomFloat(2, 11, 22),
                    'day_rate' => fake()->randomFloat(2, 90, 170),
                    'day_charge_rate' => fake()->randomFloat(2, 130, 230),
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
            'disputed' => [...$base, 'payroll_confirmation_sent_at' => now()->subDays(3), 'disputed_at' => now()->subDay(), 'dispute_reason' => 'Hours queried by client — awaiting a call back.'],
            default => $base,
        };
    }

    /**
     * A wider historical/future spread on top of the current-period ones
     * above — mirrors BrightPathSeeder's own approach, so period navigation
     * (prev/next) has other weeks to land on too.
     *
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, EducationCandidate|HealthcareCandidate>  $candidates
     */
    private function seedSpreadBookings(Collection $clients, Collection $candidates, Industry $industry, int $total): void
    {
        if ($clients->isEmpty() || $candidates->isEmpty()) {
            return;
        }

        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $industry->id)->get();
        $candidateType = $industry->slug === 'education' ? EducationCandidate::class : HealthcareCandidate::class;

        for ($i = 0; $i < $total; $i++) {
            $startDate = now()->subWeeks(6)->addDays(random_int(0, 12 * 7));
            $isPast = $startDate->isPast();

            $booking = Booking::create([
                'company_id' => $this->company->id,
                'client_id' => $clients->random()->id,
                'candidate_id' => $candidates->random()->id,
                'candidate_type' => $candidateType,
                'job_title_id' => $jobTitles->isNotEmpty() ? $jobTitles->random()->id : null,
                'consultant_id' => $this->consultants->random()->id,
                'start_date' => $startDate,
                'status' => $isPast ? 'completed' : 'upcoming',
                'hourly_rate' => fake()->randomFloat(2, 11, 22),
                'day_rate' => fake()->randomFloat(2, 90, 170),
                // The charge rate billed to the client — see the sibling
                // current-period method above, which already gets this
                // right. Without it, every booking here nets a guaranteed
                // negative gross profit (full pay cost, nothing billed to
                // cover it).
                'day_charge_rate' => fake()->randomFloat(2, 130, 230),
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
}
