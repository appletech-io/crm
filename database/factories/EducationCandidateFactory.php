<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\EducationCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationCandidate>
 */
class EducationCandidateFactory extends Factory
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
            // PAYE is the default worker type; a candidate with an umbrella
            // company should override these via 'payment_method' =>
            // PaymentMethod::Umbrella and 'payment_provider_id' in the test.
            'payment_method' => PaymentMethod::Paye,
            'bank_account_number' => fake()->numerify('########'),
            'bank_sort_code' => fake()->numerify('######'),
        ];
    }
}
