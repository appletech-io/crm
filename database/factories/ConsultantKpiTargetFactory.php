<?php

namespace Database\Factories;

use App\Models\ConsultantKpiTarget;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultantKpiTarget>
 */
class ConsultantKpiTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'industry_id' => Industry::factory(),
            'gp_target' => $this->faker->numberBetween(1000, 5000),
            'candidate_days_target' => $this->faker->numberBetween(10, 50),
            'working_candidates_target' => $this->faker->numberBetween(5, 30),
            'clients_booked_target' => $this->faker->numberBetween(3, 20),
            'rebook_rate_target' => $this->faker->randomFloat(1, 50, 90),
        ];
    }
}
