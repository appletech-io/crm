<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            IndustrySeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            JobTitleSeeder::class,
            ClientTypeSeeder::class,
            CandidateStatusSeeder::class,
            CandidatePoolSeeder::class,
            EducationQualificationsSeeder::class,
            EducationSkillSeeder::class,
            EmailTemplateSeeder::class,
            ClientSeeder::class,
            EducationCandidateSeeder::class,
            BookingSeeder::class,
            BrightPathSeeder::class,
        ]);
    }
}
