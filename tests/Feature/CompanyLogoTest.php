<?php

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('a company with no uploaded logo falls back to the default asset everywhere', function () {
    $company = Company::factory()->create(['logo' => null]);

    expect($company->logoUrl())->toBe(asset('images/appletech.png'))
        ->and($company->logoContents())->toBe(file_get_contents(public_path('images/appletech.png')))
        ->and($company->logoMimeType())->toBe('image/png');
});

test('a company with an uploaded logo serves its own file everywhere', function () {
    $contents = file_get_contents(base_path('public/images/appletech.png'));
    Storage::disk('local')->put('company-logos/acme.png', $contents);

    $company = Company::factory()->create(['logo' => 'company-logos/acme.png']);

    expect($company->logoUrl())->toBe(route('company.logo', $company))
        ->and($company->logoContents())->toBe($contents)
        ->and($company->logoMimeType())->toBe('image/png');
});

test('the public logo route serves the uploaded logo with the correct content type', function () {
    $contents = file_get_contents(base_path('public/images/appletech.png'));
    Storage::disk('local')->put('company-logos/acme.png', $contents);

    $company = Company::factory()->create(['logo' => 'company-logos/acme.png']);

    $response = $this->get(route('company.logo', $company));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->toBe($contents);
});

test('the public logo route falls back to the default logo for a company with none uploaded', function () {
    $company = Company::factory()->create(['logo' => null]);

    $response = $this->get(route('company.logo', $company));

    $response->assertOk();
    expect($response->getContent())->toBe(file_get_contents(public_path('images/appletech.png')));
});

/**
 * Regression test: a live upload recorded a `logo` path in the database
 * whose file was never actually written to disk (the write silently failed
 * — filesystems.default has 'throw' => false, so Storage::put() just
 * returns false instead of raising anything Filament would notice). Every
 * one of the three logo methods used to blow up with a TypeError the
 * moment anything tried to read the "missing" file, since Storage::get()
 * returns null for a path that doesn't exist. They must instead behave
 * exactly as if no logo were set at all.
 */
test('a company whose recorded logo path does not actually exist on disk falls back to the default, without crashing', function () {
    $company = Company::factory()->create(['logo' => 'company-logos/never-actually-uploaded.png']);

    expect($company->logoUrl())->toBe(asset('images/appletech.png'))
        ->and($company->logoContents())->toBe(file_get_contents(public_path('images/appletech.png')))
        ->and($company->logoMimeType())->toBe('image/png');

    $this->get(route('company.logo', $company))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});
