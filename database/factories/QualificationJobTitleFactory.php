<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Industry;
use App\Models\JobTitle;
use App\Models\Qualification;
use App\Models\QualificationJobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualificationJobTitle>
 */
class QualificationJobTitleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'industry_id' => Industry::factory(),
            'qualification_id' => Qualification::factory(),
            'job_title_id' => JobTitle::factory(),
        ];
    }
}
