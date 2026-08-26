<?php

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
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

test('a company with no uploaded logo uses the pre-squared default asset as its favicon', function () {
    $company = Company::factory()->create(['logo' => null]);

    expect($company->faviconUrl())->toBe(asset('images/appletech-favicon.png'));
});

test('a company with an uploaded logo gets a square, padded favicon rather than the raw (non-square) logo', function () {
    $contents = file_get_contents(base_path('public/images/appletech.png'));
    Storage::disk('local')->put('company-logos/acme.png', $contents);

    $company = Company::factory()->create(['logo' => 'company-logos/acme.png']);

    expect($company->faviconUrl())->toBe(route('company.logo.favicon', $company));

    $favicon = $company->faviconContents();
    $size = getimagesizefromstring($favicon);

    expect($favicon)->not->toBe($contents)
        ->and($size[0])->toBe($size[1]);
});

/**
 * Regression test: the live cache store is a MySQL table whose `value`
 * column rejects raw binary content (invalid UTF-8 / NUL bytes) — inserting
 * the raw PNG bytes failed with "Incorrect string value" in production,
 * even though it worked fine in tests (the array cache driver used here
 * doesn't have that constraint). Asserting the cached value is
 * base64-safe — rather than just that faviconContents() returns the right
 * bytes — is what would have actually caught this before it shipped.
 */
test('the cached favicon value is base64-safe, not raw binary, so it can be stored in a database cache table', function () {
    $contents = file_get_contents(base_path('public/images/appletech.png'));
    Storage::disk('local')->put('company-logos/acme.png', $contents);

    $company = Company::factory()->create(['logo' => 'company-logos/acme.png']);
    $company->faviconContents();

    $cached = Cache::get("favicon:{$company->logo}");

    expect($cached)->not->toBeNull()
        ->and(base64_encode(base64_decode($cached, true)))->toBe($cached);
});

test('the public favicon route serves a square png with the correct content type', function () {
    $contents = file_get_contents(base_path('public/images/appletech.png'));
    Storage::disk('local')->put('company-logos/acme.png', $contents);

    $company = Company::factory()->create(['logo' => 'company-logos/acme.png']);

    $response = $this->get(route('company.logo.favicon', $company));

    $response->assertOk()->assertHeader('Content-Type', 'image/png');

    $size = getimagesizefromstring($response->getContent());
    expect($size[0])->toBe($size[1]);
});
