<?php

namespace Database\Factories;

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
            'company_id' => \App\Models\Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
