<?php

namespace Database\Factories;

use App\Models\EducationApplication;
use App\Models\EducationCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EducationApplication>
 */
class EducationApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'education_candidate_id' => EducationCandidate::factory(),
            'email' => fake()->safeEmail(),
            'status' => 'pending',
            'token' => Str::random(32),
            'expires_on' => now()->addDays(7),
            'completed_at' => null,
            'email_verified' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_on' => now()->subDay(), 'status' => 'expired']);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
