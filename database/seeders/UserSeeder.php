<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** @var array<int, string> */
    private const CONSULTANT_NAMES = [
        'Jordan Blake',
        'Casey Whitfield',
        'Morgan Ashworth',
        'Taylor Rowntree',
    ];

    public function run(): void
    {
        $company = Company::where('name', 'applebough')->firstOrFail();
        $education = Industry::where('slug', 'education')->firstOrFail();

        User::factory()->create([
            'name' => 'Sam Site Admin',
            'email' => 'siteadmin@applebough.test',
            'company_id' => null,
        ])->assignRole('site_admin');

        $admin = User::factory()->create([
            'name' => 'Alex Administrator',
            'email' => 'admin@applebough.test',
            'company_id' => $company->id,
        ]);
        $admin->assignRole('admin');
        $admin->industries()->attach($education);

        foreach (self::CONSULTANT_NAMES as $name) {
            $consultant = User::factory()->create([
                'name' => $name,
                'email' => str($name)->slug('.').'@applebough.test',
                'company_id' => $company->id,
            ]);
            $consultant->assignRole('consultant');
            $consultant->industries()->attach($education);
        }

        $resourcer = User::factory()->create([
            'name' => 'Riley Fenwick',
            'email' => 'resourcer@applebough.test',
            'company_id' => $company->id,
        ]);
        $resourcer->assignRole('resourcer');
        $resourcer->industries()->attach($education);
    }
}
