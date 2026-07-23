<?php

namespace Database\Factories;

use App\Models\Action;
use App\Models\ActionTrigger;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionTrigger>
 */
class ActionTriggerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action_id' => Action::factory(),
            'model_type' => Client::class,
            'model_id' => Client::factory(),
            'todo_item_id' => null,
        ];
    }
}
