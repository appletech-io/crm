<?php

namespace Database\Factories;

use App\Models\ClientPool;
use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPool>
 */
class ClientPoolFactory extends Factory
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
            'name' => $this->faker->words(3, true),
            'company_pool' => false,
        ];
    }
}
