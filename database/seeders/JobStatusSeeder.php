<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Industry;
use App\Models\JobStatus;
use Illuminate\Database\Seeder;

class JobStatusSeeder extends Seeder
{
    /** @var array<string, string> */
    private const STATUSES = [
        'Open' => 'green',
        'On Hold' => 'amber',
        'Filled' => 'sky',
        'Cancelled' => 'red',
    ];

    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $industry = Industry::where('slug', 'education')->firstOrFail();

        collect(self::STATUSES)->each(fn (string $color, string $name): JobStatus => JobStatus::firstOrCreate([
            'company_id' => $company->id,
            'industry_id' => $industry->id,
            'name' => $name,
        ], [
            'color' => $color,
        ]));
    }
}
