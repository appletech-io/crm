<?php

namespace Database\Seeders;

use App\Models\ClientType;
use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Seeder;

class ClientTypeSeeder extends Seeder
{
    /** @var array<int, string> */
    private const TYPES = [
        'Primary School',
        'Secondary School',
        'Nursery',
        'Academy Trust',
        'College',
        'Pupil Referral Unit',
    ];

    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $industry = Industry::where('slug', 'education')->firstOrFail();

        foreach (self::TYPES as $name) {
            ClientType::firstOrCreate([
                'company_id' => $company->id,
                'industry_id' => $industry->id,
                'name' => $name,
            ]);
        }
    }
}
