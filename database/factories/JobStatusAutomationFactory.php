<?php

namespace Database\Factories;

use App\Models\JobStatus;
use App\Models\JobStatusAutomation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobStatusAutomation>
 */
class JobStatusAutomationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_status_id' => JobStatus::factory(),
            'to_job_status_id' => JobStatus::factory(),
            'conditions' => [],
        ];
    }
}
