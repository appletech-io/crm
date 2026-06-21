<?php

namespace Database\Factories;

use App\Models\EducationClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationClient>
 */
class EducationClientFactory extends Factory
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
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'subject' => $this->faker->word(),
            'grade_level' => $this->faker->randomElement(['Primary', 'Secondary', 'University']),
            'notes' => $this->faker->paragraph(),
        ];
    }
}
