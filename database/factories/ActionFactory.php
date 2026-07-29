<?php

namespace Database\Factories;

use App\Models\Action;
use App\Models\Client;
use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Action>
 */
class ActionFactory extends Factory
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
            'name' => $this->faker->sentence(3),
            'model_type' => Client::class,
            'assignee_type' => 'consultant',
            'assignee_role' => null,
            'conditions' => [
                ['field' => 'name', 'operator' => 'filled'],
            ],
            'todo_name' => $this->faker->sentence(4),
            'todo_description' => null,
            'todo_priority' => 'medium',
            'is_active' => true,
        ];
    }

    public function roleBased(string $role): static
    {
        return $this->state([
            'assignee_type' => 'role',
            'assignee_role' => $role,
        ]);
    }
}
