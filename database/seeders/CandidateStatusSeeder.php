<?php

namespace Database\Seeders;

use App\Models\CandidateStatus;
use App\Models\CandidateStatusAutomation;
use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Seeder;

class CandidateStatusSeeder extends Seeder
{
    /** @var array<string, string> */
    private const STATUSES = [
        'Onboarding' => 'amber',
        'Vetting' => 'blue',
        'Live' => 'emerald',
        'DNU' => 'red',
        'Offline' => 'gray',
    ];

    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $industry = Industry::where('slug', 'education')->firstOrFail();

        $statuses = collect(self::STATUSES)->map(fn (string $color, string $name): CandidateStatus => CandidateStatus::firstOrCreate([
            'company_id' => $company->id,
            'industry_id' => $industry->id,
            'name' => $name,
        ], [
            'color' => $color,
        ]));

        CandidateStatusAutomation::firstOrCreate([
            'candidate_status_id' => $statuses['Onboarding']->id,
            'to_candidate_status_id' => $statuses['Vetting']->id,
        ], [
            'completed_fields' => ['references.*', 'employmentHistories.*'],
        ]);
    }
}
