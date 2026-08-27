<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceItem>
 */
class ComplianceItemFactory extends Factory
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
            'industry_id' => Industry::factory(),
            'name' => fake()->words(3, true),
        ];
    }
}
