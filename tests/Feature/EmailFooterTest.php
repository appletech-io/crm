<?php

use App\Models\Company;
use App\Models\User;
use App\Services\Mail\EmailFooter;

test('it includes the consultants name, job title, mobile and email when a consultant is given', function () {
    $company = Company::factory()->create(['phone' => '0121 827 4646', 'website' => 'www.applebough.co.uk']);
    $consultant = User::factory()->create([
        'name' => 'Kirsty Greaves',
        'email' => 'kirsty@applebough.co.uk',
        'mobile' => '07792 810 290',
        'job_title' => 'Consultant',
    ]);

    $html = EmailFooter::render($company, $consultant);

    expect($html)->toContain('Kirsty Greaves')
        ->toContain('Consultant')
        ->toContain('0121 827 4646')
        ->toContain('07792 810 290')
        ->toContain('kirsty@applebough.co.uk')
        ->toContain('www.applebough.co.uk')
        ->toContain('cid:applebough-logo');
});

test('the logo attachment points at the real logo file with the matching content id', function () {
    $attachment = EmailFooter::logoAttachment();

    expect($attachment['path'])->toEndWith('images/applebough.png')
        ->and(file_exists($attachment['path']))->toBeTrue()
        ->and($attachment['inline'])->toBeTrue()
        ->and($attachment['contentId'])->toBe('applebough-logo');
});

test('it defaults the job title to Consultant when the user has none set', function () {
    $company = Company::factory()->create();
    $consultant = User::factory()->create(['job_title' => null]);

    $html = EmailFooter::render($company, $consultant);

    expect($html)->toContain('Consultant');
});

test('it omits the mobile row when the consultant has no mobile number', function () {
    $company = Company::factory()->create();
    $consultant = User::factory()->create(['mobile' => null]);

    $html = EmailFooter::render($company, $consultant);

    expect($html)->not->toContain('m:');
});

test('it falls back to a generic company signature when there is no consultant', function () {
    $company = Company::factory()->create(['name' => 'Applebough', 'phone' => '0121 827 4646', 'website' => 'www.applebough.co.uk']);

    $html = EmailFooter::render($company, null);

    expect($html)->toContain('Applebough')
        ->toContain('0121 827 4646')
        ->toContain('www.applebough.co.uk');
});

test('it omits the website row entirely when the company has no website set', function () {
    $company = Company::factory()->create(['website' => null]);
    $consultant = User::factory()->create();

    $html = EmailFooter::render($company, $consultant);

    expect($html)->not->toContain('w:');
});

test('it adds an https scheme to the website link but displays it without one', function () {
    $company = Company::factory()->create(['website' => 'www.applebough.co.uk']);
    $consultant = User::factory()->create();

    $html = EmailFooter::render($company, $consultant);

    expect($html)->toContain('href="https://www.applebough.co.uk"')
        ->toContain('>www.applebough.co.uk<');
});

test('it does not double up the scheme when the website already has one', function () {
    $company = Company::factory()->create(['website' => 'https://www.applebough.co.uk']);
    $consultant = User::factory()->create();

    $html = EmailFooter::render($company, $consultant);

    expect($html)->toContain('href="https://www.applebough.co.uk"')
        ->not->toContain('https://https://');
});
