<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PaymentProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProvider>
 */
class PaymentProviderFactory extends Factory
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
            'name' => fake()->company(),
            'address_1' => fake()->streetAddress(),
            'address_2' => null,
            'county' => fake()->randomElement(['West Midlands', 'Greater London', 'Greater Manchester', 'West Yorkshire']),
            'postcode' => fake()->postcode(),
        ];
    }
}
