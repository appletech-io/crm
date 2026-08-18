<?php

namespace Database\Factories;

use App\Models\ClientContactJobTitle;
use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientContactJobTitle>
 */
class ClientContactJobTitleFactory extends Factory
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
            'name' => fake()->jobTitle(),
        ];
    }
}
