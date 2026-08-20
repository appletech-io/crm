<?php

namespace Database\Factories;

use App\Enums\VacancyEmploymentType;
use App\Models\Client;
use App\Models\Company;
use App\Models\JobStatus;
use App\Models\JobTitle;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacancy>
 */
class VacancyFactory extends Factory
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
            'client_id' => Client::factory(),
            'job_title_id' => JobTitle::factory(),
            'title' => fake()->jobTitle(),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->optional()->paragraph(),
            'salary_min' => fake()->numberBetween(20000, 30000),
            'salary_max' => fake()->numberBetween(30000, 45000),
            'positions_available' => 1,
            'employment_type' => VacancyEmploymentType::Permanent->value,
            'open_for_applications' => true,
            'job_status_id' => JobStatus::factory(),
        ];
    }
}
