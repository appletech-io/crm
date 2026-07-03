<?php

namespace Database\Seeders;

use App\Models\CandidateSkill;
use App\Models\Company;
use App\Models\Industry;
use Illuminate\Database\Seeder;

class EducationSkillSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private const SKILLS = [
        'Teaching' => [
            'Phonics',
            'Lesson Planning',
            'Classroom Management',
            'Differentiated Instruction',
            'Assessment for Learning',
        ],
        'Special Educational Needs' => [
            'Autism Spectrum Support',
            'Dyslexia Support',
            'Speech and Language Support',
            'Behavioural Support',
            'EHCP Knowledge',
        ],
        'Curriculum Subjects' => [
            'Mathematics',
            'English / Literacy',
            'Science',
            'Computing / ICT',
            'Modern Foreign Languages',
            'Physical Education',
            'Art and Design',
            'Music',
            'Geography',
            'History',
        ],
        'Pastoral & Safeguarding' => [
            'Safeguarding (DSL Trained)',
            'Pastoral Care',
            'Mentoring',
            'Anti-Bullying Strategies',
        ],
        'Leadership & Administration' => [
            'Curriculum Development',
            'Staff Training',
            'Parental Engagement',
            'Data Analysis & Reporting',
            'Timetabling',
        ],
        'Technology in Education' => [
            'Interactive Whiteboards',
            'Google Classroom',
            'Microsoft Teams for Education',
            'Virtual Learning Environments',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('name', 'applebough')->first();
        $industry = Industry::where('slug', 'education')->first();

        if (! $company || ! $industry) {
            return;
        }

        foreach (self::SKILLS as $parentName => $children) {
            $parent = CandidateSkill::firstOrCreate([
                'company_id' => $company->id,
                'industry_id' => $industry->id,
                'name' => $parentName,
                'parent_id' => null,
            ]);

            foreach ($children as $childName) {
                CandidateSkill::firstOrCreate([
                    'company_id' => $company->id,
                    'industry_id' => $industry->id,
                    'name' => $childName,
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }
}
