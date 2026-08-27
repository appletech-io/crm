<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
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
            'industry_id' => Industry::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            // Not fake()->phoneNumber() — its en_US formats occasionally
            // include an "x1234" extension, which fails the phone field's
            // default tel-format validation and made tests that resubmit an
            // untouched candidate's phone intermittently fail.
            'phone' => fake()->numerify('01### ######'),
        ];
    }
}
