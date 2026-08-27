<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\JobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CandidateApplication>
 */
class CandidateApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            // Matches whichever candidate ends up set, same reasoning as
            // VacancyFactory's industry_id — keeps the default consistent
            // without forcing every test to pass company_id explicitly.
            'company_id' => fn (array $attributes): int => Candidate::find($attributes['candidate_id'])->company_id,
            'job_title_id' => JobTitle::factory(),
            'token' => Str::random(40),
            'status' => 'pending',
        ];
    }
}
