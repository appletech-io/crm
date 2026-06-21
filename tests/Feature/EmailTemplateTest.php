<?php

use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Auth;

test('email template can be created', function () {
    $industry = Industry::factory()->create(['slug' => 'education']);
    $company = Company::factory()->create();
    $company->industries()->attach($industry);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->industries()->attach($industry);
    Auth::login($user);

    $template = EmailTemplate::create([
        'name' => 'Test Template',
        'subject' => 'Test Subject',
        'body' => 'Test Body',
        'industry_id' => $industry->id,
        'company_id' => $company->id,
    ]);

    expect($template->exists)->toBeTrue()
        ->and($template->name)->toBe('Test Template');
});
