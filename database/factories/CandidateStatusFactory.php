<?php

namespace Database\Factories;

use App\Models\CandidateStatus;
use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateStatus>
 */
class CandidateStatusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'industry_id' => Industry::factory(),
            'name' => fake()->words(2, true),
            'color' => fake()->randomElement(array_keys(CandidateStatus::COLOR_OPTIONS)),
        ];
    }
}
