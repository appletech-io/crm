<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Industry;
use App\Models\SampleProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SampleProfile>
 */
class SampleProfileFactory extends Factory
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
            'path' => 'sample-profiles/'.fake()->uuid().'.pdf',
        ];
    }
}
