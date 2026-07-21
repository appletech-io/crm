<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::create([
            'name' => 'applebough',
            'trading_name' => 'Applebough Education Recruitment',
            'phone' => '0121 555 0142',
        ]);

        $educationIndustry = Industry::where('slug', 'education')->first();

        if ($educationIndustry) {
            $company->industries()->attach($educationIndustry);
        }
    }
}
