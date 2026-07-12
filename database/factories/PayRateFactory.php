<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\JobTitle;
use App\Models\PayRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayRate>
 */
class PayRateFactory extends Factory
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
            'job_title_id' => JobTitle::factory(),
            'hourly_rate' => $this->faker->randomFloat(2, 10, 50),
            'day_rate' => $this->faker->randomFloat(2, 80, 400),
            'half_day_rate' => $this->faker->randomFloat(2, 40, 200),
        ];
    }
}
