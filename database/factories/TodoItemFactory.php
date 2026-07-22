<?php

namespace Database\Factories;

use App\Enums\TodoPriority;
use App\Models\TodoItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TodoItem>
 */
class TodoItemFactory extends Factory
{
    protected $model = TodoItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'priority' => $this->faker->randomElement(TodoPriority::cases())->value,
            'completed_at' => null,
        ];
    }
}
