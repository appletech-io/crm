<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Industry;
use App\Models\JobStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobStatus>
 */
class JobStatusFactory extends Factory
{
    /**
     * A plain incrementing counter rather than fake()->unique() — this
     * value only needs to be distinct per instance created, not truly
     * random, and a large test run creating many statuses would otherwise
     * risk exhausting Faker's unique() pool.
     */
    private static int $nextSortOrder = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'industry_id' => Industry::factory(),
            'name' => fake()->words(2, true),
            'color' => fake()->randomElement(array_keys(JobStatus::COLOR_OPTIONS)),
            // A distinct default per instance (rather than everything
            // landing on 0) so a test creating several statuses gets a
            // deterministic, meaningful order for free without having to
            // set this explicitly every time.
            'sort_order' => self::$nextSortOrder++,
        ];
    }
}
