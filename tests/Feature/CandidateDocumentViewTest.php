<?php

use App\Models\User;
use App\Services\Candidates\Document;
use Carbon\CarbonInterface;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
});

test('viewing a document redirects to a freshly generated signed url resolved from the configured default disk', function () {
    config(['filesystems.default' => 's3']);

    $path = 'candidates/1/safeguarding-training.pdf';

    Storage::shouldReceive('disk')
        ->once()
        ->with('s3')
        ->andReturnSelf();
    Storage::shouldReceive('exists')
        ->once()
        ->with($path)
        ->andReturnTrue();
    Storage::shouldReceive('temporaryUrl')
        ->once()
        ->with($path, Mockery::type(CarbonInterface::class))
        ->andReturn('https://example-bucket.s3.amazonaws.com/signed-url');

    $this->get(Document::viewUrl($path))
        ->assertRedirect('https://example-bucket.s3.amazonaws.com/signed-url');
});

test('viewing a document that no longer exists on disk returns a 404', function () {
    Storage::fake('local');

    $this->get(Document::viewUrl('candidates/1/missing.pdf'))->assertNotFound();
});

test('a tampered or invalid path token returns a 404 instead of an error', function () {
    $this->get(route('documents.view', ['path' => 'not-a-valid-token']))->assertNotFound();
});

test('guests cannot view documents', function () {
    auth()->logout();

    $this->get(Document::viewUrl('candidates/1/safeguarding-training.pdf'))
        ->assertRedirect();
});

test('the view url encrypts the storage path rather than exposing it directly', function () {
    $url = Document::viewUrl('candidates/1/safeguarding-training.pdf');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['path'])->not->toContain('candidates/1/safeguarding-training.pdf');
    expect(Crypt::decryptString($query['path']))->toBe('candidates/1/safeguarding-training.pdf');
});
