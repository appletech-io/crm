<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\DocumentType;
use App\Enums\Education\Availability;
use App\Enums\Education\KeyStage;
use App\Enums\EmailTemplateType;
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
use App\Models\EmailTemplate;
use App\Models\HealthcareCandidate;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Models\Qualification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BrightPathSeeder extends Seeder
{
    private Company $company;

    private Industry $education;

    private Industry $healthcare;

    /** @var Collection<int, User> */
    private Collection $consultants;

    public function run(): void
    {
        $this->education = Industry::where('slug', 'education')->firstOrFail();
        $this->healthcare = Industry::firstOrCreate(['slug' => 'healthcare'], ['name' => 'Healthcare']);

        $this->company = Company::create([
            'name' => 'Bright Path Recruitment',
            'trading_name' => 'Bright Path Recruitment Group',
            'phone' => '0161 555 0198',
        ]);

        $this->company->industries()->attach([$this->education->id, $this->healthcare->id]);

        $this->seedUsers();
        $this->seedJobTitles();
        $this->seedClientTypes();
        $this->seedQualifications();
        $this->seedCandidateSkills();
        $this->seedCandidateStatuses();
        $this->seedCandidatePools();
        $this->seedEmailTemplates();

        $educationClients = $this->seedClients($this->education, [
            'Aspen Grove Primary School',
            'Fairview Secondary Academy',
            'Little Sprouts Nursery',
            'Cedarwood Sixth Form College',
        ]);

        $healthcareClients = $this->seedClients($this->healthcare, [
            'Northgate General Hospital',
            'Willowbrook Care Home',
            'Home First Domiciliary Care',
            'Meridian Mental Health Unit',
        ]);

        $educationCandidates = $this->seedEducationCandidates(15);
        $healthcareCandidates = $this->seedHealthcareCandidates(15);

        $this->seedBookings($educationClients, $educationCandidates, $this->education, 20);
        $this->seedBookings($healthcareClients, $healthcareCandidates, $this->healthcare, 20);
    }

    private function seedUsers(): void
    {
        $admin = User::factory()->create([
            'name' => 'Priya Anand',
            'email' => 'admin@brightpath.test',
            'company_id' => $this->company->id,
        ]);
        $admin->assignRole('admin');
        $admin->industries()->attach([$this->education->id, $this->healthcare->id]);

        foreach (['Sam Okafor', 'Lena Marsh'] as $name) {
            $consultant = User::factory()->create([
                'name' => $name,
                'email' => str($name)->slug('.').'@brightpath.test',
                'company_id' => $this->company->id,
            ]);
            $consultant->assignRole('consultant');
            $consultant->industries()->attach([$this->education->id, $this->healthcare->id]);
        }

        $resourcer = User::factory()->create([
            'name' => 'Dana Whitlock',
            'email' => 'resourcer@brightpath.test',
            'company_id' => $this->company->id,
        ]);
        $resourcer->assignRole('resourcer');
        $resourcer->industries()->attach([$this->education->id, $this->healthcare->id]);

        $this->consultants = User::role('consultant')->where('company_id', $this->company->id)->get();
    }

    private function seedJobTitles(): void
    {
        $titles = [
            $this->education->id => ['Teacher', 'Teaching Assistant', 'Cover Supervisor', 'SEN Teaching Assistant', 'Nursery Nurse'],
            $this->healthcare->id => ['Registered Nurse', 'Healthcare Assistant', 'Support Worker', 'Care Coordinator', 'Domiciliary Carer'],
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
            $this->education->id => ['Primary School', 'Secondary School', 'Nursery', 'College'],
            $this->healthcare->id => ['Hospital', 'Care Home', 'Domiciliary Care Provider', 'Mental Health Unit'],
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

    private function seedQualifications(): void
    {
        $qualifications = [
            $this->education->id => ['Teacher - QTS', 'ECT', 'Teaching Assistant', 'HLTA', 'Early Years'],
            $this->healthcare->id => ['NMC Registered Nurse', 'HCPC Registered', 'Care Certificate', 'NVQ Level 2 Health & Social Care', 'NVQ Level 3 Health & Social Care'],
        ];

        foreach ($qualifications as $industryId => $names) {
            foreach ($names as $name) {
                Qualification::firstOrCreate([
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
            $this->education->id => ['Phonics', 'Classroom Management', 'SEN Support', 'Safeguarding'],
            $this->healthcare->id => ['Medication Administration', 'Wound Care', 'Manual Handling', 'Dementia Care', 'Palliative Care'],
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

    private function seedCandidateStatuses(): void
    {
        $statuses = [
            'Onboarding' => 'amber',
            'Vetting' => 'blue',
            'Live' => 'emerald',
            'DNU' => 'red',
            'Offline' => 'gray',
        ];

        foreach ([$this->education->id, $this->healthcare->id] as $industryId) {
            foreach ($statuses as $name => $color) {
                CandidateStatus::firstOrCreate([
                    'company_id' => $this->company->id,
                    'industry_id' => $industryId,
                    'name' => $name,
                ], ['color' => $color]);
            }
        }
    }

    private function seedCandidatePools(): void
    {
        CandidatePool::firstOrCreate([
            'company_id' => $this->company->id,
            'industry_id' => $this->education->id,
            'name' => 'Long Term Cover Available',
        ], ['company_pool' => true]);

        CandidatePool::firstOrCreate([
            'company_id' => $this->company->id,
            'industry_id' => $this->healthcare->id,
            'name' => 'Night Shift Available',
        ], ['company_pool' => true]);
    }

    private function seedEmailTemplates(): void
    {
        foreach ([$this->education->id, $this->healthcare->id] as $industryId) {
            EmailTemplate::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $industryId,
                'type' => EmailTemplateType::Application->value,
            ], [
                'name' => EmailTemplateType::Application->label(),
                'subject' => 'Complete your application with Bright Path',
                'body' => '<p>Hi {firstname},</p><p>Thanks for registering. Please complete your application here: {application_link}</p><p>This link expires on {expiry_date}.</p>',
            ]);

            EmailTemplate::firstOrCreate([
                'company_id' => $this->company->id,
                'industry_id' => $industryId,
                'type' => EmailTemplateType::PayrollConfirmation->value,
            ], [
                'name' => EmailTemplateType::PayrollConfirmation->label(),
                'subject' => 'Timesheet for {client_name} — {week_start} to {week_end}',
                'body' => '<p>Dear {client_contact_name},</p><p>Please review and confirm the timesheet for {client_name}.</p><table>{day_breakdown}</table><p>{payroll_confirmation_link}</p>',
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
                'key_stages' => collect(KeyStage::cases())->random(2)->map(fn (KeyStage $c) => $c->value)->values()->all(),
            ]);

            $client->contacts()->create([
                'company_id' => $this->company->id,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->unique()->safeEmail(),
                'main_contact' => true,
                'booking_contact' => true,
            ]);

            foreach ($jobTitles->random(min(2, $jobTitles->count())) as $jobTitle) {
                PayRate::create([
                    'company_id' => $this->company->id,
                    'model_type' => Client::class,
                    'model_id' => $client->id,
                    'job_title_id' => $jobTitle->id,
                    'hourly_rate' => fake()->randomFloat(2, 15, 25),
                    'day_rate' => fake()->randomFloat(2, 120, 220),
                    'half_day_rate' => fake()->randomFloat(2, 65, 120),
                ]);
            }

            ClientActivity::create([
                'user_id' => $this->consultants->random()->id,
                'model_type' => Client::class,
                'model_id' => $client->id,
                'type' => ActivityType::Call->value,
                'note' => 'Called to confirm requirements for the coming weeks.',
                'contacted' => true,
                'created_at' => Carbon::now()->subDays(random_int(1, 30)),
            ]);

            return $client;
        });
    }

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

    /** @return Collection<int, EducationCandidate> */
    private function seedEducationCandidates(int $total): Collection
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
                'qualification_id' => $qualifications->random()->id,
                'key_stages' => collect(KeyStage::cases())->random(2)->map(fn (KeyStage $c) => $c->value)->values()->all(),
                'availability' => collect(Availability::cases())->random(2)->map(fn (Availability $c) => $c->value)->values()->all(),
                'barred_list_check' => $tier === 'dnu' ? 'no' : 'yes',
                'right_to_work_type' => 'passport',
            ]);

            $this->attachCommonCandidateData($candidate, $skills, $statuses, $tier);

            return $candidate;
        });
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

    /**
     * @param  EducationCandidate|HealthcareCandidate  $candidate
     * @param  Collection<int, CandidateSkill>  $skills
     * @param  Collection<string, CandidateStatus>  $statuses
     */
    private function attachCommonCandidateData($candidate, Collection $skills, Collection $statuses, string $tier): void
    {
        if ($skills->isNotEmpty()) {
            $candidate->skills()->attach($skills->random(min(2, $skills->count()))->pluck('id'));
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
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, EducationCandidate|HealthcareCandidate>  $candidates
     */
    private function seedBookings(Collection $clients, Collection $candidates, Industry $industry, int $total): void
    {
        $jobTitles = JobTitle::where('company_id', $this->company->id)->where('industry_id', $industry->id)->get();
        $candidateType = $industry->slug === 'education' ? EducationCandidate::class : HealthcareCandidate::class;

        for ($i = 0; $i < $total; $i++) {
            $startDate = now()->subWeeks(6)->addDays(random_int(0, 10 * 7));
            $isPast = $startDate->isPast();

            $booking = Booking::create([
                'company_id' => $this->company->id,
                'client_id' => $clients->random()->id,
                'candidate_id' => $candidates->random()->id,
                'candidate_type' => $candidateType,
                'job_title_id' => $jobTitles->random()->id,
                'consultant_id' => $this->consultants->random()->id,
                'start_date' => $startDate,
                'status' => $isPast ? 'completed' : 'upcoming',
                'hourly_rate' => fake()->randomFloat(2, 11, 20),
                'day_rate' => fake()->randomFloat(2, 90, 160),
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
