<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IndustrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Industry::create([
            'name' => 'Education',
            'slug' => 'education',
            'clientable_type' => \App\Models\EducationClient::class,
        ]);
    }
}
