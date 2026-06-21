<?php

namespace Database\Factories;

use App\Models\EducationBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationBooking>
 */
class EducationBookingFactory extends Factory
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
            'education_client_id' => \App\Models\EducationClient::factory(),
            'education_candidate_id' => \App\Models\EducationCandidate::factory(),
            'start_date' => fake()->date(),
            'end_date' => fake()->optional()->date(),
            'status' => fake()->randomElement(['provisional', 'confirmed', 'cancelled', 'completed']),
        ];
    }
}
