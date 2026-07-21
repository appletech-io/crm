<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
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
            'name' => $this->faker->company(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'postcode' => $this->faker->postcode(),
            'county' => $this->faker->randomElement(['West Midlands', 'Greater London', 'Greater Manchester', 'West Yorkshire']),
            'phone' => '01'.$this->faker->numerify('#########'),
            'website' => $this->faker->url(),
            'notes' => $this->faker->paragraph(),
        ];
    }
}
