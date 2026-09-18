<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientLocation>
 */
class ClientLocationFactory extends Factory
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
            'name' => $this->faker->streetName().' Site',
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'county' => $this->faker->randomElement(['West Midlands', 'Greater London', 'Greater Manchester', 'West Yorkshire']),
            'postcode' => $this->faker->postcode(),
            'phone' => '01'.$this->faker->numerify('#########'),
            'is_default' => false,
        ];
    }
}
