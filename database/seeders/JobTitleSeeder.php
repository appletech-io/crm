<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Industry;
use App\Models\JobTitle;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    /** @var array<int, string> */
    private const TITLES = [
        'Teacher',
        'Cover Teacher',
        'Cover Supervisor',
        'Higher Level Teaching Assistant',
        'Teaching Assistant',
        'SEN Teaching Assistant',
        'Learning Support Assistant',
        'Nursery Nurse',
        'Lunchtime Supervisor',
        'Exam Invigilator',
        'SENDCo',
        'Instructor',
    ];

    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $industry = Industry::where('slug', 'education')->firstOrFail();

        foreach (self::TITLES as $name) {
            JobTitle::firstOrCreate([
                'company_id' => $company->id,
                'industry_id' => $industry->id,
                'name' => $name,
            ]);
        }
    }
}
