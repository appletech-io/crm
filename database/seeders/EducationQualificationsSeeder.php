<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Qualification;
use Illuminate\Database\Seeder;

class EducationQualificationsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $industry = Industry::where('slug', 'education')->firstOrFail();

        $qualifications = [
            '3rd Year',
            'Teacher - QTS',
            'Final Year Student',
            'ECT',
            'Fully Qualified',
            'FE Qualified',
            'Overseas Comparable',
            'HLTA',
            'Cover Supervisor',
            'Instructor',
            'Teaching Assistant',
            'Lunchtime Supervisor',
            'Unqualified Teaching Assistant',
            'Nursery Nurse',
            'Early Years',
            'Childcare Practitioner L1',
            'Childcare Practitioner L2',
            'Childcare Practitioner L3',
            'Childcare Practitioner L4',
            'Childcare Practitioner L5',
            'Childcare Practitioner L6',
            'Childcare Practitioner L7',
            'Other',
            'PGCE',
            'SEN-Teaching Assistant',
            'UNI - BTEC in EY',
            'SENDco',
        ];

        foreach ($qualifications as $name) {
            Qualification::firstOrCreate([
                'company_id' => $company->id,
                'industry_id' => $industry->id,
                'name' => $name,
            ]);
        }
    }
}
