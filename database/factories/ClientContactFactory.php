<?php

namespace Database\Factories;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\EducationClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientContact>
 */
class ClientContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'education_client_id' => EducationClient::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'main_contact' => false,
            'timesheet_contact' => false,
            'invoice_contact' => false,
            'booking_contact' => false,
        ];
    }
}
