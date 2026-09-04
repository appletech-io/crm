<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Industry;
use App\Models\ReferenceForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceForm>
 */
class ReferenceFormFactory extends Factory
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
            'name' => fake()->words(2, true),
            'is_statement_only' => false,
            'needs_position_and_organisation' => true,
        ];
    }

    public function statementOnly(): static
    {
        return $this->state(fn (): array => [
            'is_statement_only' => true,
            'needs_position_and_organisation' => false,
        ]);
    }
}
