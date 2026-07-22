<?php

namespace Database\Factories;

use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateStatusAutomation>
 */
class CandidateStatusAutomationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_status_id' => CandidateStatus::factory(),
            'to_candidate_status_id' => CandidateStatus::factory(),
            'conditions' => [],
        ];
    }
}
