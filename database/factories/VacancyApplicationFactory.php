<?php

namespace Database\Factories;

use App\Models\EducationCandidate;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VacancyApplication>
 */
class VacancyApplicationFactory extends Factory
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
            'match_strength' => null,
        ];
    }
}
