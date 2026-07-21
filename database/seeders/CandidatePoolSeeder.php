<?php

namespace Database\Seeders;

use App\Models\CandidatePool;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Database\Seeder;

class CandidatePoolSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $industry = Industry::where('slug', 'education')->firstOrFail();
        $consultants = User::role('consultant')->where('company_id', $company->id)->get();

        CandidatePool::firstOrCreate([
            'company_id' => $company->id,
            'industry_id' => $industry->id,
            'name' => 'SEN Specialists',
        ], [
            'company_pool' => true,
        ]);

        CandidatePool::firstOrCreate([
            'company_id' => $company->id,
            'industry_id' => $industry->id,
            'name' => 'Early Years Ready',
        ], [
            'company_pool' => true,
        ]);

        CandidatePool::firstOrCreate([
            'company_id' => $company->id,
            'industry_id' => $industry->id,
            'name' => 'Long Term Cover Available',
        ], [
            'company_pool' => true,
        ]);

        CandidatePool::firstOrCreate([
            'company_id' => $company->id,
            'industry_id' => $industry->id,
            'name' => "Newly Qualified Teachers — {$consultants->first()->name}'s Pool",
        ], [
            'user_id' => $consultants->first()->id,
            'company_pool' => false,
        ]);
    }
}
