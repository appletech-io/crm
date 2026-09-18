<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserQuickLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserQuickLink>
 */
class UserQuickLinkFactory extends Factory
{
    protected $model = UserQuickLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => $this->faker->words(2, true),
            'icon' => 'heroicon-o-star',
            'url' => $this->faker->url(),
            'position' => 0,
        ];
    }
}
