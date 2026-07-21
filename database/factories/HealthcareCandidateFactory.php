<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HealthcareCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthcareCandidate>
 */
class HealthcareCandidateFactory extends Factory
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
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
