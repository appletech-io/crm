<?php

namespace Database\Factories;

use App\Models\EducationCandidate;
use App\Models\Vacancy;
use App\Models\VacancyPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VacancyPlacement>
 */
class VacancyPlacementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vacancy_id' => Vacancy::factory(),
            'candidate_type' => EducationCandidate::class,
            'candidate_id' => EducationCandidate::factory(),
            'actual_salary' => fake()->numberBetween(20000, 40000),
            'placed_at' => now(),
        ];
    }
}
