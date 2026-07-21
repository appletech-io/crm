<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\Education\KeyStage;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientType;
use App\Models\Company;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\PayRate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ClientSeeder extends Seeder
{
    /** @var array<int, string> */
    private const CLIENT_NAMES = [
        'Oakwood Primary School',
        "St. Margaret's C of E Academy",
        'Riverside Community College',
        'Bright Beginnings Nursery',
        'Greenfield Secondary School',
        'Trinity Academy Trust',
        'Meadowbank Infant School',
        'Hillcrest Pupil Referral Unit',
        'Little Explorers Nursery',
        'Kingswood Academy',
        'Elmwood Junior School',
        "St. Joseph's Catholic Primary",
        'Northgate Sixth Form College',
        'Sunnyside Early Years Centre',
    ];

    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $industry = Industry::where('slug', 'education')->firstOrFail();
        $clientTypes = ClientType::where('company_id', $company->id)->get();
        $jobTitles = JobTitle::where('company_id', $company->id)->get();
        $consultants = User::role('consultant')->where('company_id', $company->id)->get();

        foreach (self::CLIENT_NAMES as $name) {
            $client = Client::factory()->create([
                'company_id' => $company->id,
                'industry_id' => $industry->id,
                'name' => $name,
                'client_type_id' => $clientTypes->random()->id,
                'consultant_id' => $consultants->random()->id,
                'key_stages' => collect(KeyStage::cases())
                    ->random(random_int(1, 3))
                    ->map(fn (KeyStage $stage): string => $stage->value)
                    ->values()
                    ->all(),
            ]);

            $this->createContacts($client, $company->id);
            $this->createChargeRates($client, $company->id, $jobTitles);
            $this->createActivities($client, $consultants);
        }
    }

    private function createContacts(Client $client, int $companyId): void
    {
        $contactCount = random_int(1, 3);

        $client->contacts()->create([
            'company_id' => $companyId,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'main_contact' => true,
            'booking_contact' => true,
        ]);

        if ($contactCount >= 2) {
            $client->contacts()->create([
                'company_id' => $companyId,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->unique()->safeEmail(),
                'timesheet_contact' => true,
            ]);
        }

        if ($contactCount === 3) {
            $client->contacts()->create([
                'company_id' => $companyId,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->unique()->safeEmail(),
                'invoice_contact' => true,
            ]);
        }
    }

    /** @param  Collection<int, JobTitle>  $jobTitles */
    private function createChargeRates(Client $client, int $companyId, $jobTitles): void
    {
        foreach ($jobTitles->random(random_int(2, 3)) as $jobTitle) {
            PayRate::create([
                'company_id' => $companyId,
                'model_type' => Client::class,
                'model_id' => $client->id,
                'job_title_id' => $jobTitle->id,
                'hourly_rate' => fake()->randomFloat(2, 15, 25),
                'day_rate' => fake()->randomFloat(2, 120, 220),
                'half_day_rate' => fake()->randomFloat(2, 65, 120),
            ]);
        }
    }

    /** @param  Collection<int, User>  $consultants */
    private function createActivities(Client $client, $consultants): void
    {
        $notes = [
            ActivityType::Call->value => 'Called to confirm cover requirements for next half term.',
            ActivityType::Email->value => 'Sent updated compliance pack and rate card.',
            ActivityType::Meeting->value => 'Visited site to review booking process with the office manager.',
            ActivityType::Note->value => 'Client prefers candidates with SEN experience where possible.',
        ];

        foreach (fake()->randomElements(array_keys($notes), random_int(2, 4)) as $type) {
            ClientActivity::create([
                'user_id' => $consultants->random()->id,
                'model_type' => Client::class,
                'model_id' => $client->id,
                'type' => $type,
                'note' => $notes[$type],
                'contacted' => in_array($type, [ActivityType::Call->value, ActivityType::Email->value, ActivityType::Meeting->value], true),
                'created_at' => Carbon::now()->subDays(random_int(1, 60)),
            ]);
        }
    }
}
