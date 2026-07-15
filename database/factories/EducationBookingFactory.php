<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Company;
use App\Models\EducationBooking;
use App\Models\EducationCandidate;
use App\Models\EducationClient;
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
            'company_id' => Company::factory(),
            'education_client_id' => EducationClient::factory(),
            'candidate_id' => EducationCandidate::factory(),
            'candidate_type' => EducationCandidate::class,
            'start_date' => fake()->date(),
            'end_date' => fake()->optional()->date(),
            'status' => BookingStatus::Upcoming,
        ];
    }
}
