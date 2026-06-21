<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = \App\Models\Company::create([
            'name' => 'applebough',
        ]);

        $educationIndustry = \App\Models\Industry::where('slug', 'education')->first();

        if ($educationIndustry) {
            $company->industries()->attach($educationIndustry);
        }
    }
}
